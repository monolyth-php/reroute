<?php

namespace Monolyth\Reroute;

use League\Pipeline\StageInterface;

/**
 * A simple wrapper to honour the StageInterface contract, until either League
 * fixes their interface, or we can support PHP 7 (which has anonymous classes).
 */
class Pipe implements StageInterface
{
    /**
     * @var callable
     * The callable to wrap.
     */
    private $callable;

    /**
     * @param callable $callable The callable to wrap.
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @param mixed $payload Invoke this pipe using $payload.
     * @return mixed Whatever this pipe's callable returns.
     */
    public function __invoke($payload)
    {
        return call_user_func($this->callable, $payload);
    }

    /**
     * Front to `__invoke` to statisfy the implemented interface.
     *
     * @param mixed $payload
     * @return mixed
     * @see Reroute\Pipe::__invoke
     */
    public function process($payload)
    {
        return $this($payload);
    }
}

