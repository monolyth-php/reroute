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

    public function testStateVerb()
    {
        $router = new Router;
        $router->state('test', new Flat('/', ['POST']), function() {});
        $state = $router->resolve('/', 'POST');
        $this->assertInstanceOf('Reroute\State', $state);
        $this->assertEquals('POST', $state->verb());
    }
}

