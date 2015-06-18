<?php

namespace Reroute;

use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use BadMethodCallException;

class State
{
    private $host;
    private $url;
    private $state;
    private $verb = null;
    private $arguments = [];
    private $group = null;

    public function __construct($host, Url $url, callable $state)
    {
        $this->host = $host;
        $url->setHost($host);
        $this->url = $url;
        $this->state = $state;
    }

    public function group($group = null)
    {
        if (isset($group)) {
            $this->group = $group;
        }
        return $this->group;
    }

    public function match($url, $verb)
    {
        $arguments = $this->url->match($url, $verb);
        if (!is_null($arguments)) {
            $this->arguments = $arguments;
            $this->verb = $verb;
            return true;
        }
        return false;
    }

    public function run()
    {
        $call = $this->state;
        do {
            if (is_object($call) && method_exists($call, '__invoke')) {
                $reflection = new ReflectionMethod($call, '__invoke');
            } else {
                $reflection = new ReflectionFunction($call);
            }
            $parameters = $reflection->getParameters();
            $arguments = [];
            foreach ($parameters as $value) {
                $arguments[$value->name] = $value->name == 'VERB' ?
                    $this->verb :
                    null;
            }
            // Fill all named arguments from the route match:
            $args = $this->arguments;
            $remove = [];
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
            $call = call_user_func_array($call, $args);
            $this->arguments = [];
        } while (is_callable($call));
        return $call;
    }

    public function verb()
    {
        return $this->verb;
    }

    public function url()
    {
        return $this->url;
    }
}

