<?php

use Reroute\Router;
use Reroute\Url\Flat;
use Reroute\Url\Regex;
use Reroute\Url\Legacy;
use Reroute\Url\Angular;
use Reroute\Url\Braces;
use Reroute\Url\Nomatch;

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

    public function unnamedParameter()
    {
        $router = new Router;
        $router->state('test', new Regex("/(\d+)/"), function ($id) {
            return $id;
        });
        $state = $router->resolve('/1/');
        $id = $state->run();
        $this->assetEquals(1, $id);
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

    public function testRouteGroup()
    {
        $router = new Router;
        $router->group('test', function ($router) {
            $router->state('foo', new Flat('/bar/'), function () {});
        });
        $state = $router->resolve('/bar/');
        $this->assertEquals('test', $state->group());
    }

    public function testRouteGroups()
    {
        $router = new Router;
        $router->group('foo', function ($router) {
            $router->group('bar', function ($router) {
                $router->state('test', new Flat('/test/'), function () {});
            });
        });
        $state = $router->resolve('/test/');
        $this->assertEquals(['foo', 'bar'], $state->group());
    }

    public function testRouteHost()
    {
        $router = new Router;
        $router->host('http://foo.com', function ($router) {
            $router->state('foo', new Flat('/foo/'), function () {
                return 'foo';
            });
        });
        $router->host('http://bar.com', function ($router) {
            $router->state('bar', new Flat('/bar/'), function () {
                return 'bar';
            });
        });
        $state = $router->resolve('http://foo.com/foo/');
        $this->assertEquals('foo', $state->run());
        $state = $router->resolve('http://foo.com/bar/');
        $this->assertEquals(null, $state);
        $state = $router->resolve('http://bar.com/bar/');
        $this->assertEquals('bar', $state->run());
        $state = $router->resolve('http://bar.com/foo/');
        $this->assertEquals(null, $state);
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

    public function testBraces()
    {
        $router = new Router;
        $router->state('angular', new Braces('/{str}/'), function () {});
        $state = $router->resolve('/somestring/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testNomatchUrl()
    {
        $router = new Router;
        $router->state('404', new Nomatch, function() {});
        $state = $router->get('404');
        $this->assertInstanceOf('Reroute\State', $state);
    }
}

