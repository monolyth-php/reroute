<?php

namespace Monolyth\Reroute\Tests;

use Monolyth\Reroute\Router;
use Zend\Diactoros\ServerRequestFactory;
use Psr\Http\Message\RequestInterface;

class RouterTest
{
    public function __wakeup()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /**
     * We can resolve a route and it returns the desired state {?}.
     */
    public function testBasicRoute(Router $router)
    {
        $router->when('/')->then('foo', 'Hello world!');
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'Hello world!');
    }

    /**
     * When passing unnamed parameters, they get injected {?}.
     */
    public function unnamedParameter(Router $router)
    {
        $router->when("/(\d+)/")->then('foo', function ($id) {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/1/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 1);
    }

    /**
     * When passing named parameters, they get injected {?}.
     */
    public function testNamedParameter(Router $router)
    {
        $router = new Router;
        $router->when("/(?'id'\d+)/")->then('foo', function ($id) {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/1/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 1);
    }

    /**
     * When passing named parameters, we can inject them in any order we like
     * {?}.
     */
    public function testParameterOrder(Router $router)
    {
        $router = new Router;
        $router->when("/(?'first'\w+)/(?'last'\w+)/")
               ->then('foo', function ($last, $first) {
                    return "$first $last";
               });
        $_SERVER['REQUEST_URI'] = '/john/doe/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'john doe');
    }

    /**
     * When injecting the current request it can be at any place in the argument
     * list of the callback {?}.
     */
    public function testRequestInRandomPlace(Router $router)
    {
        $router = new Router;
        $router->when("/(?'foo'\w+)/(\w+)/")
               ->then('foo', function ($bar, RequestInterface $request, $foo) {
                    $VERB = $request->getMethod();
                    return "$bar $VERB $foo";
               });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'bar GET foo');
    }

    /**
     * When matching any query parameters should be ignored {?}.
     */
    public function testIgnoreGetParameters(Router $router)
    {
        $router->when('/')->then('foo', function () { return 'ok'; });
        $_SERVER['REQUEST_URI'] = '/?foo=bar';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'ok');
    }

    /**
     * When querying for an undefined state a DomainException is thrown {?}.
     */
    public function testInvalidStateThrowsException(Router $router)
    {
        $e = null;
        try {
            $state = $router->get('invalid');
        } catch (\DomainException $e) {
        }
        yield assert($e instanceof \DomainException);
    }

    /**
     * Routes can be nested using chaining {?}.
     */
    public function testRouteNesting(Router $router)
    {
        $router = new Router;
        $router->when('/foo/')
               ->when('/bar/')->then('foo', function () { return 'ok'; });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'ok');
    }

    /**
     * Routes can be nested using callbacks {?}.
     */
    public function testRouteCallbackNesting(Router $router)
    {
        $router->when('/foo/', function ($router) {
            $router->when('/bar/')->then('foo', function () {
                return 'ok';
            });
        });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'ok');
    }

    /**
     * Routers can have multiple domains {?} and URLs only match the defined
     * domain {?}. This works for multiple routes {?} {?}.
     */
    public function testRouteHost(Router $router)
    {
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
        yield assert($state == 'foo');
        $_SERVER['REQUEST_URI'] = '/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert(is_null($state));
        $_SERVER['HTTP_HOST'] = 'bar.com';
        $_SERVER['REQUEST_URI'] = '/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'bar');
        $_SERVER['REQUEST_URI'] = '/foo/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert(is_null($state));
    }

    /**
     * Routes can use Angular-style parameters {?}.
     */
    public function testAngular(Router $router)
    {
        $router->when('/:angular/')->then('foo', function ($angular) {
            return $angular;
        });
        $_SERVER['REQUEST_URI'] = '/somestring/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'somestring');
    }

    /**
     * Routes can use braces-style parameters (e.g. Symfony) {?}.
     */
    public function testBraces(Router $router)
    {
        $router = new Router;
        $router->when('/{braces}/')->then('foo', function ($braces) {
            return $braces;
        });
        $_SERVER['REQUEST_URI'] = '/somestring/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == 'somestring');
    }

    /**
     * Routers can define 'fake' routes for error handling {?}.
     */
    public function testNomatchUrl(Router $router)
    {
        $router = new Router;
        $router->when(null)->then('404', '404');
        $state = $router->get('404');
        yield assert($state([], ServerRequestFactory::fromGlobals()) == '404');
    }

    /**
     * When generating a route, the domain is prepended if it differs from the
     * current domain {?}. However, if it's the same by default it is
     * omitted {?}.
     */
    public function testGenerate(Router $router)
    {
        $router->when("http://foo.com/(?'p1':\w+)/{p2}/:p3/")
               ->then('test', function () {});
        $url = $router->generate(
            'test',
            ['p1' => 'foo', 'p2' => 'bar', 'p3' => 'baz']
        );
        yield assert($url == 'http://foo.com/foo/bar/baz/');
        $_SERVER['HTTP_HOST'] = 'foo.com';
        $router = new Router;
        $router->when("http://foo.com/(?'p1':\w+)/{p2}/:p3/")
               ->then('test', function () {});
        $url = $router->generate(
            'test',
            ['p1' => 'foo', 'p2' => 'bar', 'p3' => 'baz']
        );
        yield assert($url == '/foo/bar/baz/');
    }

    /**
     * Routers can have a pipeline where arguments can be injected {?}.
     */
    public function testPipeWithUrlArguments(Router $router)
    {
        ob_start();
        $router->when('/{foo}/{bar}/')
            ->pipe(function ($request, $bar, $foo) {
                echo $foo;
                echo $bar;
                return $request;
            })
            ->then('test', 'ok');
        $_SERVER['REQUEST_URI'] = '/1/2/';
        echo $router(ServerRequestFactory::fromGlobals());
        yield assert(ob_get_clean() == '12ok');
    }

    /**
     * We can override the action on a state and inject another action {?}.
     */
    public function testActionOverride(Router $router)
    {
        ob_start();
        $router->when('/{foo}/{bar}/')
            ->then('ok', function ($foo, $bar) {
                return $foo.$bar;
            })->post(function ($bar, callable $GET) {
                echo $bar;
                return $GET;
            });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        echo $router(ServerRequestFactory::fromGlobals());
        yield assert(ob_get_clean() == 'barfoobar');
    }

    /**
     * When matching a state with a default argument (regex-style), it matches
     * either with {?} or without that argument being passed {?}.
     */
    public function testDefaultArgument(Router $router)
    {
        $router->when("/(?'id'\d+/)?")->then(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state === "2");
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state === "1");
    }

    /**
     * When matching a state with a default argument (Angular-style), it matches
     * either with {?} or without that argument being passed {?}.
     */
    public function testDefaultArgumentAngular(Router $router)
    {
        $router->when("/:id?/")->then(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state === "2");
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state == "1");
    }

    /**
     * When matching a state with a default argument (braces-style), it matches
     * either with {?} or without that argument being passed {?}.
     */
    public function testDefaultArgumentBraces(Router $router)
    {
        $router->when("/{id}?/")->then(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state === "2");
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        yield assert($state === "1");
    }
}

