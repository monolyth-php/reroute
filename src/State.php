<?php

namespace Reroute;

use Exception;
use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\Response\EmptyResponse;

class State
{
    private $actions;
    private $arguments = [];

    public $name;

    public function __construct($name, $state)
    {
        $this->name = $name;
        if (!is_callable($state)) {
            $state = $this->makeCallable($state);
        }
        $this->actions = ['GET' => $state, 'POST' => $state];
    }

    public function __invoke($arguments, RequestInterface $request)
    {
        $method = $request->getMethod();
        if (!isset($this->actions[$method])) {
            return new EmptyResponse(405);
        }
        $call = $this->actions[$method];
        do {
            $parser = new ArgumentsParser($call);
            $args = $parser->parse($arguments, $request);
            foreach ($args as &$value) {
                if (is_string($value)
                    && $this->isHttpAction(substr($value, 1))
                ) {
                    $key = substr($value, 1);
                    if ($key == $method) {
                        throw new EndlessStateLoopException;
                    }
                    if (isset($this->actions[$key])) {
                        $value = $this->actions[$key];
                    } else {
                        $value = new EmptyResponse(405);
                    }
                }
            }
            $call = call_user_func_array($call, $args);
        } while (is_callable($call));
        return $call;
    }

    public function getCallback($method = 'GET')
    {
        return isset($this->actions[$method]) ?
            $this->actions[$method] :
            null;
    }

    public function addCallback($method, $state)
    {
        $state = $this->makeCallable($state);
        $this->actions[$method] = $state;
    }

    private function makeCallable($state)
    {
        return function () use ($state) {
            return $state;
        };
    }

    private function isHttpAction($action)
    {
        return in_array(
            $action,
            ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS']
        );
    }
}

