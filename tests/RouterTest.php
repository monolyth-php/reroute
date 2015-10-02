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
        $router->when('/')->then('foo', 'Hello world');
        $state = $router->resolve('/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testBasicRoute()
    {
        $router = new Router;
        $router->when('/')->then('foo', 'Hello world!');
        $state = $router->resolve('/');
        $this->assertEquals('Hello world!', $state());
    }

    public function unnamedParameter()
    {
        $router = new Router;
        $router->when("/(\d+)/")->then('foo', function ($id) {
            return $id;
        });
        $state = $router->resolve('/1/');
        $this->assetEquals(1, $state());
    }

    public function testNamedParameter()
    {
        $router = new Router;
        $router->when("/(?'id'\d+)/")->then('foo', function ($id) {
            return $id;
        });
        $state = $router->resolve('/1/');
        $this->assertEquals(1, $state());
    }

    public function testParameterOrder()
    {
        $router = new Router;
        $router->when("/(?'first'\w+)/(?'last'\w+)/")
               ->then('foo', function ($last, $first) {
                    return "$first $last";
               });
        $state = $router->resolve('/john/doe/');
        $this->assertEquals('john doe', $state());
    }

    public function testVerbInRandomPlace()
    {
        $router = new Router;
        $router->when("/(?'foo'\w+)/(\w+)/")
               ->then('foo', function ($bar, $VERB, $foo) {
                    return "$bar $VERB $foo";
               });
        $state = $router->resolve('/foo/bar/');
        $this->assertEquals('bar GET foo', $state());
    }

    public function testIgnoreGetParameters()
    {
        $router = new Router;
        $router->when('/')->then('foo', function () {});
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

    public function testRouteNesting()
    {
        $router = new Router;
        $router->when('/foo/')
               ->when('/bar/')->then('foo', function () {});
        $state = $router->resolve('/foo/bar/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testRouteCallbackNesting()
    {
        $router = new Router;
        $router->when('/foo/', function ($router) {
            $router->when('/bar/')->then('foo', function () {});
        });
        $state = $router->resolve('/foo/bar/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testRouteHost()
    {
        $router = new Router;
        $router->when('http://foo.com/', function ($router) {
            $router->when('/foo/')->then('foo', function () {
                return 'foo';
            });
        });
        $router->when('http://bar.com/', function ($router) {
            $router->when('/bar/')->then('foo', function () {
                return 'bar';
            });
        });
        $state = $router->resolve('http://foo.com/foo/');
        $this->assertEquals('foo', $state());
        $state = $router->resolve('http://foo.com/bar/');
        $this->assertEquals(null, $state);
        $state = $router->resolve('http://bar.com/bar/');
        $this->assertEquals('bar', $state());
        $state = $router->resolve('http://bar.com/foo/');
        $this->assertEquals(null, $state);
    }

    public function testAngular()
    {
        $router = new Router;
        $router->when('/:angular/')->then('foo', function () {});
        $state = $router->resolve('/somestring/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testBraces()
    {
        $router = new Router;
        $router->when('/{braces}/')->then('foo', function () {});
        $state = $router->resolve('/somestring/');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testNomatchUrl()
    {
        $router = new Router;
        $router->when(null)->then('404', function() {});
        $state = $router->get('404');
        $this->assertInstanceOf('Reroute\State', $state);
    }

    public function testGenerate()
    {
        $router = new Router;
        $router->when("http://foo.com/(?'p1':\w+)/{p2}/:p3/")
               ->then('test', function () {});
        $url = $router->generate(
            'test',
            ['p1' => 'foo', 'p2' => 'bar', 'p3' => 'baz']
        );
        $this->assertEquals('http://foo.com/foo/bar/baz/', $url);
        $_SERVER['HTTP_HOST'] = 'foo.com';
        $url = $router->generate(
            'test',
            ['p1' => 'foo', 'p2' => 'bar', 'p3' => 'baz']
        );
        $this->assertEquals('/foo/bar/baz/', $url);
    }
}

