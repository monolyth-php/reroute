<?php

namespace Reroute;

use Exception;

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

    public function __toString()
    {
        try {
            $call = $this->state;
            do {
                $parser = new ArgumentsParser($call);
                $args = $parser->parse($this->arguments);
                $call = call_user_func_array($call, $args);
                $this->arguments = [];
            } while (is_callable($call));
            return $call;
        } catch (Exception $e) {
            return $e->getMessage()."\n".$e->getFile()."\n".$e->getLine();
        }
    }
}

