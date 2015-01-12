<?php

namespace reroute;
use ReflectionFunction;
use BadMethodCallException;

class State
{
    private $url;
    private $state;
    private $parameters = [];
    private $arguments = [];
    private $group = null;

    public function __construct($url, callable $state)
    {
        $this->url = $url;
        $this->state = $state;
    }

    public function arguments(array $arguments)
    {
        $this->arguments = $arguments;
    }

    public function group($group = null)
    {
        if (isset($group)) {
            $this->group = $group;
        }
        return $this->group;
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
            } else {
                throw new BadMethodCallException;
            }
        }
        $call = $this->state;
        while (is_callable($call)) {
            $call = call_user_func_array($call, $arguments);
        }
        return $call;
    }
}

