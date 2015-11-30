<?php

namespace Reroute;

use League\Pipeline\StageInterface;

class Stage implements StageInterface
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function __invoke($payload)
    {
        return call_user_func($this->callable, $payload);
    }

    public function process($payload)
    {
        return $this($payload);
    }
}

