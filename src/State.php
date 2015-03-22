<?php

namespace Reroute;

use ReflectionFunction;
use ReflectionException;
use BadMethodCallException;

class State
{
    private $url;
    private $state;
    private $verb = null;
    private $parameters = [];
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
        $reflection = new ReflectionFunction($this->state);
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
        $call = $this->state;
        while (is_callable($call)) {
            $call = call_user_func_array($call, $arguments);
        }
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

