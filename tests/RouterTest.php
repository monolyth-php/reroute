<?php

namespace Reroute\Tests;

use PHPUnit_Framework_TestCase;
use Reroute\Router;
use Reroute\Url\Flat;
use Reroute\Url\Regex;
use Reroute\Url\Legacy;
use Reroute\Url\Angular;
use Reroute\Url\Braces;
use Reroute\Url\Nomatch;
use Zend\Diactoros\ServerRequestFactory;
use Psr\Http\Message\RequestInterface;

class RouterTest extends PHPUnit_Framework_TestCase
{
    protected function setup()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testBasicRoute()
    {
        $router = new Router;
        $router->when('/')->then('foo', 'Hello world!');
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('Hello world!', $state);
    }

    public function unnamedParameter()
    {
        $router = new Router;
        $router->when("/(\d+)/")->then('foo', function ($id) {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/1/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assetEquals(1, $state());
    }

    public function testNamedParameter()
    {
        $router = new Router;
        $router->when("/(?'id'\d+)/")->then('foo', function ($id) {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/1/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals(1, $state);
    }

    public function testParameterOrder()
    {
        $router = new Router;
        $router->when("/(?'first'\w+)/(?'last'\w+)/")
               ->then('foo', function ($last, $first) {
                    return "$first $last";
               });
        $_SERVER['REQUEST_URI'] = '/john/doe/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('john doe', $state);
    }

    public function testRequestInRandomPlace()
    {
        $router = new Router;
        $router->when("/(?'foo'\w+)/(\w+)/")
               ->then('foo', function ($bar, RequestInterface $request, $foo) {
                    $VERB = $request->getMethod();
                    return "$bar $VERB $foo";
               });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('bar GET foo', $state);
    }

    public function testIgnoreGetParameters()
    {
        $router = new Router;
        $router->when('/')->then('foo', function () { return 'ok'; });
        $_SERVER['REQUEST_URI'] = '/?foo=bar';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('ok', $state);
    }

    /**
     * @expectedException DomainException
     */
    public function testInvalidStateThrowsException()
    {
        $router = new Router;
        $e = null;
        $state = $router->get('invalid');
    }

    public function testRouteNesting()
    {
        $router = new Router;
        $router->when('/foo/')
               ->when('/bar/')->then('foo', function () { return 'ok'; });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('ok', $state);
    }

    public function testRouteCallbackNesting()
    {
        $router = new Router;
        $router->when('/foo/', function ($router) {
            $router->when('/bar/')->then('foo', function () {
                return 'ok';
            });
        });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('ok', $state);
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
        $_SERVER['HTTP_HOST'] = 'foo.com';
        $_SERVER['REQUEST_URI'] = '/foo/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('foo', $state);
        $_SERVER['REQUEST_URI'] = '/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals(null, $state);
        $_SERVER['HTTP_HOST'] = 'bar.com';
        $_SERVER['REQUEST_URI'] = '/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('bar', $state);
        $_SERVER['REQUEST_URI'] = '/foo/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals(null, $state);
    }

    public function testAngular()
    {
        $router = new Router;
        $router->when('/:angular/')->then('foo', function ($angular) {
            return $angular;
        });
        $_SERVER['REQUEST_URI'] = '/somestring/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('somestring', $state);
    }

    public function testBraces()
    {
        $router = new Router;
        $router->when('/{braces}/')->then('foo', function ($braces) {
            return $braces;
        });
        $_SERVER['REQUEST_URI'] = '/somestring/';
        $state = $router(ServerRequestFactory::fromGlobals());
        $this->assertEquals('somestring', $state);
    }

    public function testNomatchUrl()
    {
        $router = new Router;
        $router->when(null)->then('404', '404');
        $state = $router->get('404');
        $this->assertEquals(
            '404',
            $state([], ServerRequestFactory::fromGlobals())
        );
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
        $router = new Router;
        $router->when("http://foo.com/(?'p1':\w+)/{p2}/:p3/")
               ->then('test', function () {});
        $url = $router->generate(
            'test',
            ['p1' => 'foo', 'p2' => 'bar', 'p3' => 'baz']
        );
        $this->assertEquals('/foo/bar/baz/', $url);
    }

    public function testPipeWithUrlArguments()
    {
        $router = new Router;
        $this->expectOutputString('12ok');
        $router->when('/{foo}/{bar}/')
            ->pipe(function ($request, $bar, $foo) {
                echo $foo;
                echo $bar;
                return $request;
            })
            ->then('test', 'ok');
        $_SERVER['REQUEST_URI'] = '/1/2/';
        echo $router(ServerRequestFactory::fromGlobals());
    }
}

