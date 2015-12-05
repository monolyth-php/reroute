<?php

namespace Reroute;

use ReflectionMethod;
use ReflectionFunction;
use Psr\Http\Message\RequestInterface;

class ArgumentsParser
{
    public function __construct(callable $call)
    {
        if (is_object($call) && method_exists($call, '__invoke')) {
            $reflection = new ReflectionMethod($call, '__invoke');
        } else {
            $reflection = new ReflectionFunction($call);
        }
        $parameters = $reflection->getParameters();
        $arguments = [];
        $request = 'Psr\Http\Message\RequestInterface';
        foreach ($parameters as $value) {
            if ($class = $value->getClass()
                and $class->implementsInterface($request)
            ) {
                $arguments["RequestInterface"] = true;
            } else {
                $arguments[$value->name] = null;
            }
        }
        $this->arguments = $arguments;
    }

    public function parse($matches, RequestInterface $request)
    {
        $remove = [];
        $args = isset($matches) ? $matches : [];
        while (false !== ($curr = each($args))) {
            if (is_string($curr['key'])
                && array_key_exists($curr['key'], $this->arguments)
            ) {
                $this->arguments[$curr['key']] = $curr['value'];
                $remove[] = $curr['key'];
                $next = each($args);
                $remove[] = $next['key'];
            }
        }
        foreach ($remove as $key) {
            unset($args[$key]);
        }
        if (isset($this->arguments['RequestInterface'])) {
            $this->arguments['RequestInterface'] = $request;
        }
        // For remaining arguments, use the next available index:
        array_walk($this->arguments, function (&$value) use (&$args) {
            if (is_null($value) && $args) {
                $value = array_shift($args);
            }
        });
        // Remove unset arguments:
        $args = [];
        foreach ($this->arguments as $arg) {
            if (isset($arg)) {
                $args[] = $arg;
            }
        }
        return $args;
    }
}

