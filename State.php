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

    public function __construct($url, callable $state)
    {
        $this->url = $url;
        $this->state = $state;
    }

    public function arguments(array $arguments)
    {
        $this->arguments = $arguments;
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
        call_user_func_array($this->state, $arguments);
    }
}

