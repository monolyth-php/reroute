<?php

use Reroute\Router;

class StateTest extends PHPUnit_Framework_TestCase
{
    public function testStateInGroup()
    {
        $router = new Router;
        $router->group('foo', function ($router) {
            $router->state('bar', '/', function() {});
        });
        $state = $router->resolve('/');
        $this->assertEquals('foo', $state->group());
    }
}

