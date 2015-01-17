<?php

use Reroute\Router;
use Reroute\Url\Flat;
use Reroute\Url\Regex;
use Reroute\Url\Legacy;
use Reroute\Url\Angular;

class RouterTest extends PHPUnit_Framework_TestCase
{
    public function testResolveReturnsState()
    {
        $router = new Router;
        $router->state('home', new Flat('/'), function () {
            return "Hello world!";
        });
        $state = $router->resolve('/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testBasicRoute()
    {
        $router = new Router;
        $router->state('home', new Flat('/'), function () {
            return "Hello world!";
        });
        $state = $router->resolve('/');
        $out = $state->run();
        $this->assertEquals('Hello world!', $out);
    }

    public function testNamedParameter()
    {
        $router = new Router;
        $router->state('user', new Regex("/(?'id'\d+)/"), function ($id) {
            return $id;
        });
        $state = $router->resolve('/1/');
        $id = $state->run();
        $this->assertEquals(1, $id);
    }

    public function testParameterOrder()
    {
        $router = new Router;
        $router->state(
            'order',
            new Regex("/(?'first'\w+)/(?'last'\w+)/"),
            function ($last, $first) {
                return compact('last', 'first');
            }
        );
        $state = $router->resolve('/john/doe/');
        extract($state->run());
        $this->assertEquals('john', $first);
        $this->assertEquals('doe', $last);
    }

    public function testIgnoreGetParameters()
    {
        $router = new Router;
        $router->state('home', new Flat('/'), function () {});
        $state = $router->resolve('/?foo=bar');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testInvalidStateThrowsException()
    {
        $router = new Router;
        $e = null;
        $state = null;
        try {
            $state = $router->get('invalid');
        } catch (DomainException $e) {
        }
        $this->assertEquals(null, $state);
        $this->assertInstanceOf('DomainException', $e);
    }

    public function testRouteUnder()
    {
        $router = new Router;
        $router->under('/foo', function ($router) {
            $router->state('bar', new Flat('/bar/'), function () {});
        });
        $state = $router->resolve('/foo/bar/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testLegacy()
    {
        $router = new Router;
        $router->state('legacy', new Legacy('/(%s:str)/'), function () {});
        $state = $router->resolve('/somestring/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testAngular()
    {
        $router = new Router;
        $router->state('angular', new Angular('/:str/'), function () {});
        $state = $router->resolve('/somestring/');
        $this->assertInstanceOf('Reroute\State', $state);
    }
}

