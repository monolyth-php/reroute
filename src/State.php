<?php

namespace Reroute;

use Exception;
use ReflectionMethod;
use ReflectionFunction;
use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\Response\EmptyResponse;

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

    public function __construct($name, $state)
    {
        $this->name = $name;
        if (!is_callable($state)) {
            $state = $this->makeCallable($state);
        }
        $this->actions = ['GET' => $state, 'POST' => $state];
    }

    public function __invoke($arguments, RequestInterface $request)
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

    public function getCallback($method = 'GET')
    {
        return isset($this->actions[$method]) ?
            $this->actions[$method] :
            null;
    }

    public function addCallback($method, $state)
    {
        $state = $this->makeCallable($state);
        $this->actions[$method] = $state;
    }

    private function makeCallable($state)
    {
        return function () use ($state) {
            return $state;
        };
    }

    private function isHttpAction($action)
    {
        return in_array(
            $action,
            ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS']
        );
    }

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

