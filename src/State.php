<?php

namespace Reroute;

class State
{
    private $state;
    private $arguments = [];

    public $name;

    public function __construct($name, $state, array $arguments)
    {
        $this->name = $name;
        if (!is_callable($state)) {
            $tmp = $state;
            $state = function () use ($tmp) {
                return $tmp;
            };
        }
        $this->state = $state;
        $this->arguments = $arguments;
    }

    public function run()
    {
        $call = $this->state;
        do {
            $parser = new ArgumentsParser($call);
            $args = $parser->parse($this->arguments);
            $call = call_user_func_array($call, $args);
            $this->arguments = [];
        } while (is_callable($call));
        return $call;
    }
}

