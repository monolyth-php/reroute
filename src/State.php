<?php

namespace Reroute;

use ReflectionMethod;
use ReflectionFunction;
use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\Response\EmptyResponse;

/**
 * The State class. This is an internal wrapper representing a state belonging
 * to a certain URL, as defined by your Reroute\Router.
 */
class State
{
    /**
     * @var array
     * Hash of supported actions and their associated state callbacks for this
     * state.
     */
    private $actions;

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
     * Constructor. Normally one does not instantiate states directly.
     *
     * @param null|string The (preferably unique) name of the state.
     * @param mixed $state A valid state.
     */
    public function __construct($name, $state)
    {
        $this->name = $name;
        $state = $this->makeCallable($state);
        $this->actions = ['GET' => $state, 'POST' => $state];
    }

    /**
     * Invoke this state. States are invoked until they return something
     * non-invokable.
     *
     * @param array $arguments All matched URL parameters.
     * @param Psr\Http\Message\RequestInterface $request The current request.
     * @return mixed Whatever the state eventually resolves to.
     */
    public function __invoke(array $arguments, RequestInterface $request)
    {
        $method = $request->getMethod();
        if (!isset($this->actions[$method])) {
            return new EmptyResponse(405);
        }
        $call = $this->actions[$method];
        $this->request = $request;
        do {
            $args = $this->parseArguments($call, $arguments);
            foreach ($args as &$value) {
                if (is_string($value)
                    && $this->isHttpAction(substr($value, 1))
                ) {
                    $key = substr($value, 1);
                    if ($key == $method) {
                        throw new EndlessStateLoopException;
                    }
                    if (isset($this->actions[$key])) {
                        $value = $this->actions[$key];
                    } else {
                        $value = new EmptyResponse(405);
                    }
                }
            }
            $call = call_user_func_array($call, $args);
        } while (is_callable($call));
        return $call;
    }

    /**
     * Retrieves the registered internal state for a certain action. Not really
     * used at the moment but might come in handy sometimes.
     *
     * @param string $method The action for which to retrieve the internal
     *  state. Defaults to `"GET"`.
     * @return mixed The found state on success, or null if no such method was
     *  defined.
     */
    public function getAction($method = 'GET')
    {
        return isset($this->actions[$method]) ?
            $this->actions[$method] :
            null;
    }

    /**
     * Add a state callback for an method. If the state is something
     * non-callable it is auto-wrapped in a Closure.
     *
     * @param string $method The method to add this state for.
     * @param mixed $state The state to respond with.
     */
    public function addCallback($method, $state)
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
    private function makeCallable($state)
    {
        if (is_callable($state)) {
            return $state;
        }
        return function () use ($state) {
            return $state;
        };
    }

    /**
     * Internal helper method to check if the specified action is supported.
     *
     * @param string $action An HTTP action verb (e.g. `"GET"`).
     * @return boolean True if supported, else false.
     */
    private function isHttpAction($action)
    {
        return in_array(
            $action,
            ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS']
        );
    }

    /**
     * Internal helper to parse arguments for a callable, inject the correct
     * values if found and remove unused parameters.
     *
     * @param callabable $call The callable to generate an argument list for.
     * @param array $matches Array of matched parameters from the current
     *  request URI.
     * @return array An array of parameters $call can be called with.
     */
    private function parseArguments(callable $call, array $matches)
    {
        if (is_object($call) && method_exists($call, '__invoke')) {
            $reflection = new ReflectionMethod($call, '__invoke');
        } else {
            $reflection = new ReflectionFunction($call);
        }
        $parameters = $reflection->getParameters();
        $arguments = [];
        $request = 'Psr\Http\Message\RequestInterface';
        foreach ($parameters as $value) {
            if ($class = $value->getClass()
                and $class->implementsInterface($request)
            ) {
                $arguments["RequestInterface"] = $this->request;
            } elseif ($value->isCallable()) {
                $arguments[$value->name] = isset($this->actions[$value->name]) ?
                    $this->actions[$value->name] :
                    new EmptyResponse(405);
            } else {
                $arguments[$value->name] = null;
            }
        }
        $remove = [];
        $args = isset($matches) ? $matches : [];
        while (false !== ($curr = each($args))) {
            if (is_string($curr['key'])
                && array_key_exists($curr['key'], $arguments)
            ) {
                $arguments[$curr['key']] = $curr['value'];
                $remove[] = $curr['key'];
                $next = each($args);
                $remove[] = $next['key'];
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
        return $args;
    }
}

