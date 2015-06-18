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
            $this->arguments = ['VERB' => $verb] + $arguments;
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
                $arguments[$value->name] = null;
            }
            // Fill all named arguments from the route match:
            $args = $this->arguments;
            //  Start with -1, first argument is always $VERB
            $ignore = -1;
            array_walk(
                $arguments,
                function (&$value, $index) use (&$args, &$ignore) {
                    if (isset($args[$index])) {
                        $value = $args[$index];
                        unset($args[$index], $args[$ignore]);
                    }
                    ++$ignore;
                }
            );
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

