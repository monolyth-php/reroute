<?php

namespace Reroute;

use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use BadMethodCallException;

class State
{
    private $url;
    private $state;
    private $verb = null;
    private $arguments = [];
    private $group = null;

    public function __construct($url, callable $state)
    {
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
            foreach ($parameters as $key => $value) {
                if (isset($this->arguments[$value->name])) {
                    $arguments[] = $this->arguments[$value->name];
                } elseif (isset($this->arguments[$key])) {
                    $arguments[] = $this->arguments[$key];
                } elseif ($value->name == 'VERB') {
                    $arguments[] = $this->verb;
                } else {
                    try {
                        $arguments[] = $value->getDefaultValue();
                    } catch (ReflectionException $e) {
                        throw new BadMethodCallException;
                    }
                }
            }
            $call = call_user_func_array($call, $arguments);
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

