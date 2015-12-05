<?php

namespace Reroute;

use Exception;
use Psr\Http\Message\RequestInterface;

class State
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

    public function __invoke($arguments, RequestInterface $request)
    {
        $call = $this->state;
        $parser = new ArgumentsParser($call);
        do {
            $args = $parser->parse($arguments, $request);
            $call = call_user_func_array($call, $args);
        } while (is_callable($call));
        return $call;
    }

    public function getCallback()
    {
        return $this->state;
    }
}

