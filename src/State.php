<?php

namespace Reroute;

use Exception;
use League\Pipeline\StageInterface;

class State implements StageInterface
{
    private $state;
    private $arguments = [];

    public $name;

    public function __construct($name, $state)
    {
        $this->name = $name;
        if (!is_callable($state)) {
            $this->state = function () use ($state) {
                return $state;
            };
        } else {
            $this->state = $state;
        }
    }

    public function __invoke($payload)
    {
        $call = $this->state;
        do {
            $parser = new ArgumentsParser($call);
            $args = $parser($payload)['arguments'];
            $call = call_user_func_array($call, $args);
        } while (is_callable($call));
        return $call;
    }

    public function getCallback()
    {
        return $this->state;
    }
}

