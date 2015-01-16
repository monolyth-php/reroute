<?php

use Reroute\Router;
use Reroute\Url\Flat;

class StateTest extends PHPUnit_Framework_TestCase
{
    public function testStateInGroup()
    {
        $router = new Router;
        $router->group('foo', function ($router) {
            $router->state('bar', new Flat('/'), function() {});
        });
        $state = $router->resolve('/');
        $this->assertEquals('foo', $state->group());
    }
}

