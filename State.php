<?php

namespace reroute;
use ReflectionFunction;
use BadMethodCallException;

class State
{
    private $state;
    private $parameters = [];
    private $arguments = [];

    public function __construct(callable $state)
    {
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

