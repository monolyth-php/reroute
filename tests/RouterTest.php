<?php

use Reroute\Router;

class RouterTest extends PHPUnit_Framework_TestCase
{
    public function testResolveReturnsState()
    {
        $router = new Router;
        $router->state('home', '/', function() {
            return "Hello world!";
        });
        $state = $router->resolve('/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testBasicRoute()
    {
        $router = new Router;
        $router->state('home', '/', function() {
            return "Hello world!";
        });
        $state = $router->resolve('/');
        $out = $state->run();
        $this->assertEquals('Hello world!', $out);
    }

    public function testNamedParameter()
    {
        $router = new Router;
        $router->state('user', "/(?'id'\d+)/", function($id) {
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
            "/(?'first'\w+)/(?'last'\w+)/",
            function($last, $first) {
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
        $router->state('home', '/', function() {});
        $state = $router->resolve('/?foo=bar');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testGenerateUrl()
    {
        $router = new Router;
        $router->state('home', '/', function() {});
        $url = $router->url('home');
        $this->assertEquals('http://localhost/', $url);
    }

    public function testGenerateUrlWithParameters()
    {
        $router = new Router;
        $router->state(
            'params',
            "/(?'first'\w+)/(?'last'\w+)/",
            function($last, $first) {}
        );
        $url = $router->url('params', ['first' => 'john', 'last' => 'doe']);
        $this->assertEquals('http://localhost/john/doe/', $url);
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
            $router->state('bar', '/bar/', function () {});
        });
        $state = $router->resolve('/foo/bar/');
        $this->assertInstanceOf('Reroute\State', $state);
    }
}

