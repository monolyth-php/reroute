<?php

namespace Reroute;

class State
{
    private $state;
    private $verb;
    private $arguments = [];

    public function __construct($state, array $arguments)
    {
        if (!is_callable($state)) {
            $tmp = $state;
            $state = function () use ($tmp) {
                return $tmp;
            };
        }
        $this->state = $state;
        $this->arguments = $arguments;
        $this->verb = isset($_SERVER['REQUEST_METHOD']) ?
            $_SERVER['REQUEST_METHOD'] :
            'GET';
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

