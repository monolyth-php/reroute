<?php

namespace Monolyth\Reroute;

use Exception;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionType;
use ReflectionNamedType;
use ReflectionClass;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use League\Pipeline\PipelineBuilder;
use League\Pipeline\Pipeline;
use Laminas\Diactoros\Response\EmptyResponse;

/**
 * The State class. This is a wrapper representing a state belonging to a
 * certain URL, as defined by your Monolyth\Reroute\Router.
 */
class State
{
    /**
     * @var array
     * Hash of supported actions and their associated state callbacks for this
     * state.
     */
    private $actions = [];

    /**
     * @var string
     * The (preferably unique) name of this state.
     */
    public $name;

    /**
     * @var Psr\Http\Message\RequestInterface
     * The current request.
     */
    private $request;

    /**
     * @var array
     */
    private $pipeline = [];

    /**
     * @var string
     */
    private $url;

    /**
     * @var array
     */
    private static $arguments = [];

    /**
     * Constructor. Normally one does not instantiate states directly.
     *
     * @param string $url The URL this state is for.
     * @param string|null The (preferably unique) name of the state.
     */
    public function __construct(string $url, ?string $name = null)
    {
        $this->url = $url;
        $this->name = $name;
    }

    /**
     * Invoke this state. States are invoked until they return something
     * non-invokable.
     *
     * @param array $arguments All matched URL parameters.
     * @param Psr\Http\Message\RequestInterface $request The current request.
     * @return Psr\Http\Message\ReponseInterface
     * @throws Monolyth\Reroute\ResolvedStateMustImplementResponseInterfaceException
     *  if the the resolved state does not implement
     *  Psr\Http\Message\ResponseInterface.
     * @throws Monolyth\Reroute\MethodNotSupportedException if the requested
     *  action tries to inject an unsupported HTTP method.
     * @throws Monolyth\Reroute\EndlessStateLoopException if the given state
     *  returns itself.
     */
    public function __invoke(array $arguments, RequestInterface $request) : ResponseInterface
    {
        $method = $request->getMethod();
        if ($method == 'HEAD') {
            $method = 'GET';
        }
        if (!isset($this->actions[$method])) {
            $allowed = implode(', ', array_keys($this->actions));
            return new EmptyResponse(405, ['Allow' => $allowed]);
        }
        self::$arguments = $arguments;
        $pipeline = new PipelineBuilder;
        foreach ($this->pipeline as $pipe) {
            $pipeline->add($pipe);
        }
        $pipe = $pipeline->build()->process($request);
        if ($pipe instanceof ResponseInterface) {
            return $pipe;
        }
        $call = $this->actions[$method];
        $this->request = $request;
        do {
            $args = $this->parseArguments($call, $arguments);
            foreach ($args as &$value) {
                if (is_string($value) && $this->isHttpAction(substr($value, 1))) {
                    $key = substr($value, 1);
                    if ($key == $method) {
                        throw new EndlessStateLoopException;
                    }
                    if (isset($this->actions[$key])) {
                        $value = $this->actions[$key];
                    } else {
                        throw new MethodNotSupportedException($key);
                    }
                }
            }
            $call = call_user_func_array($call, $args);
        } while (is_callable($call));
        if (!($call instanceof ResponseInterface)) {
            throw new ResolvedStateMustImplementResponseInterfaceException;
        }
        if ($request->getMethod() == 'HEAD') {
            $body = $call->getBody()->getContents();
            $headers = $call->getHeaders();
            return new EmptyResponse(200, $headers + ['Content-Length' => strlen($body)]);
        }
        return $call;
    }

    /**
     * Get the read-only associated URL for the state.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Retrieves the registered internal state for a certain action. Not really
     * used at the moment but might come in handy sometimes.
     *
     * @param string $method The action for which to retrieve the internal
     *  state. Defaults to `"GET"`.
     * @return mixed The found action on success, or null if no such verb was
     *  defined.
     */
    public function action(string $method = 'GET')
    {
        return $this->actions[$method] ?? null;
    }

    /**
     * Add a response for _any_ supported HTTP verb.
     *
     * @param mixed $state
     * @return Monolyth\Reroute\State
     */
    public function any($state) : State
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'] as $verb) {
            $this->addCallback($verb, $state);
        }
        return $this;
    }

    /**
     * Add a response for the GET verb. If no POST handler was defined, this
     * response also doubles for a POST.
     *
     * @param mixed $state
     * @return Monolyth\Reroute\State
     */
    public function get($state) : State
    {
        $this->addCallback('GET', $state);
        if (!isset($this->actions['POST'])) {
            $this->addCallback('POST', $state);
        }
        return $this;
    }

    /**
     * Add a response for the POST verb.
     *
     * @param mixed $state
     * @return Monolyth\Reroute\State
     */
    public function post($state) : State
    {
        $this->addCallback('POST', $state);
        return $this;
    }

    /**
     * Add a response for the PUT verb.
     *
     * @param mixed $state
     * @return Monolyth\Reroute\State
     */
    public function put($state) : State
    {
        $this->addCallback('PUT', $state);
        return $this;
    }

    /**
     * Add a response for the DELETE verb.
     *
     * @param mixed $state
     * @return Monolyth\Reroute\State
     */
    public function delete($state) : State
    {
        $this->addCallback('DELETE', $state);
        return $this;
    }

    /**
     * Add a response for the OPTIONS verb.
     *
     * @param mixed $state
     * @return Monolyth\Reroute\State
     */
    public function options($state) : State
    {
        $this->addCallback('OPTIONS', $state);
        return $this;
    }

    /**
     * Adds callables to the pipeline. The first argument is the payload (i.e.
     * request or response object). Subsequent arguments are taken from the
     * currently matched URL parameters.
     *
     * @param callable ...$stages Callable stages to add.
     * @return Monolyth\Reroute\State
     * @throws InvalidArgumentException if any of the additional argument wasn't
     *  matched by name in the URL.
     */
    public function pipe(callable ...$stages) : State
    {
        foreach ($stages as $stage) {
            if (!($stage instanceof StageInterface)) {
                $stage = new Pipe(function ($payload) use ($stage) {
                    if ($payload instanceof ResponseInterface) {
                        return $payload;
                    }
                    if ($stage instanceof Closure) {
                        $reflection = new ReflectionFunction($stage);
                    } elseif (is_array($stage)) {
                        $reflection = new ReflectionMethod($stage[0], $stage[1]);
                    } else {
                        $reflection = new ReflectionMethod($stage, '__invoke');
                    }
                    $parameters = $reflection->getParameters();
                    $args = [];
                    foreach ($parameters as $key => $param) {
                        if (!$key) {
                            $args[] = $payload;
                        } elseif (isset(self::$arguments[$param->name])) {
                            $args[] = self::$arguments[$param->name];
                        } else {
                            throw new InvalidArgumentException("Pipe expects variable {$param->name}, but it is not present in the URL being resolved.");
                        }
                    }
                    return call_user_func_array($stage, $args);
                });
            }
            $this->pipeline[] = $stage;
        }
        return $this;
    }

    /**
     * Return the current array of pipelines.
     *
     * @return array
     */
    public function getPipeline()
    {
        return $this->pipeline;
    }

    /**
     * Like `Monolyth\Reroute\Stage::pipe`, only the stages get unshifted onto
     * the stack. Mostly used internally to add pipes from parent stages at
     * runtime.
     *
     * @param callable ...$stages Callable stages to add.
     * @return Monolyth\Reroute\State
     * @throws InvalidArgumentException if any of the additional argument wasn't
     *  matched by name in the URL.
     * @see Monolyth\Reroute\Stage::pipe
     */
    public function pipeUnshift(callable ...$stages) : State
    {
        $pipeline = $this->pipeline;
        $this->pipeline = [];
        $this->pipe(...$stages);
        $this->pipeline = array_merge($this->pipeline, $pipeline);
        return $this;
    }

    /**
     * Add a state callback for a method. If the state is something
     * non-callable it is auto-wrapped in a Closure.
     *
     * @param string $method The method to add this state for.
     * @param mixed $state The state to respond with.
     * @return void
     */
    private function addCallback(string $method, $state) : void
    {
        $state = $this->makeCallable($state);
        $this->actions[$method] = $state;
    }

    /**
     * Helper method to wrap a state in a callback if it is not callable yet.
     *
     * @param mixed $state The state to wrap.
     * @return callable The original state if already callable, else the state
     *  wrapped in a closure.
     */
    private function makeCallable($state) : callable
    {
        if (is_callable($state)) {
            return $state;
        }
        return function () use ($state) {
            if (is_string($state) && class_exists($state)) {
                $state = new $state;
            }
            return $state;
        };
    }

    /**
     * Internal helper method to check if the specified action is supported.
     *
     * @param string $action An HTTP action verb (e.g. `"GET"`).
     * @return boolean True if supported, else false.
     */
    private function isHttpAction($action) : bool
    {
        return in_array($action, ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS']);
    }

    /**
     * Internal helper to parse arguments for a callable, inject the correct
     * values if found and remove unused parameters.
     *
     * @param callabable $call The callable to generate an argument list for.
     * @param array $matches Array of matched parameters from the current
     *  request URI.
     * @return array An array of parameters $call can be called with.
     * @throws Monolyth\Reroute\MethodNotSupportedException if an argument
     *  refers to a HTTP method not supported by this state.
     */
    private function parseArguments(callable $call, array $matches) : array
    {
        if (is_object($call) && method_exists($call, '__invoke')) {
            $reflection = new ReflectionMethod($call, '__invoke');
        } elseif (is_array($call) && method_exists($call[0], $call[1])) {
            $reflection = new ReflectionMethod($call[0], $call[1]);
        } else {
            $reflection = new ReflectionFunction($call);
        }
        $parameters = $reflection->getParameters();
        $arguments = [];
        foreach ($parameters as $value) {
            if ($type = $value->getType()
                and $class = $type->__toString()
                and (class_exists($class) or interface_exists($class))
                and (new ReflectionClass($class))->implementsInterface(RequestInterface::class)
            ) {
                $arguments[$value->name] = $this->request;
            } elseif ($type and $this->isCallable($type)) {
                if (isset($this->actions[$value->name])) {
                    $arguments[$value->name] = $this->actions[$value->name];
                } else {
                    throw new MethodNotSupportedException($value->name);
                }
            } else {
                $arguments[$value->name] = null;
            }
        }
        $remove = [];
        $args = isset($matches) ? $matches : [];
        $justMatched = false;
        foreach ($args as $key => $value) {
            if (is_numeric($key) && $justMatched) {
                $remove[] = $key;
            }
            $justMatched = false;
            if (is_string($key) && array_key_exists($key, $arguments)) {
                $arguments[$key] = $value;
                $remove[] = $key;
                $justMatched = true;
            }
        }
        foreach ($remove as $key) {
            unset($args[$key]);
        }
        // For remaining arguments, use the next available index:
        array_walk($arguments, function (&$value) use (&$args) {
            if (is_null($value) && $args) {
                $value = array_shift($args);
            }
        });
        // Remove unset arguments:
        $args = [];
        foreach ($arguments as $arg) {
            if (isset($arg)) {
                $args[] = $arg;
            }
        }
        array_walk($args, function (&$value) {
            if (is_string($value)) {
                $value = preg_replace("@/$@", '', $value);
            }
        });
        return $args;
    }

    private function isCallable(ReflectionType $type) : bool
    {
        $types = $type instanceof ReflectionUnionType
            ? $type->getTypes()
            : [$type];
        return in_array('callable', array_map(fn(ReflectionNamedType $t) => $t->getName(), $types));
    }
}

