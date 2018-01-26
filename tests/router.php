<?php

use Monolyth\Reroute\Router;
use Zend\Diactoros\ServerRequestFactory;
use Psr\Http\Message\RequestInterface;

return function ($test) : Generator {
    $test->beforeEach(function () use (&$router) {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router = new Router('http://localhost');
    });

    /**We can resolve a route and it returns the desired state */
    yield function () use (&$router) {
        $router->when('/')->then('foo', 'Hello world!');
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'Hello world!');
    };

    /** When passing unnamed parameters, they get injected */
    yield function () use (&$router) {
        $router->when("/(\d+)/")->then('foo', function ($id) {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/1/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 1);
    };

    /** When passing named parameters, they get injected */
    yield function () use (&$router) {
        $router->when("/(?'id'\d+)/")->then('foo', function ($id) {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/1/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 1);
    };

    /** When passing named parameters, we can inject them in any order we like */
    yield function () use (&$router) {
        $router->when("/(?'first'\w+)/(?'last'\w+)/")
               ->then('foo', function ($last, $first) {
                    return "$first $last";
               });
        $_SERVER['REQUEST_URI'] = '/john/doe/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'john doe');
    };

    /** When injecting the current request it can be at any place in the argument list of the callback */
    yield function () use (&$router) {
        $router->when("/(?'foo'\w+)/(\w+)/")
               ->then('foo', function ($bar, RequestInterface $request, $foo) {
                    $VERB = $request->getMethod();
                    return "$bar $VERB $foo";
               });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'bar GET foo');
    };

    /** When matching any query parameters should be ignored */
    yield function () use (&$router) {
        $router->when('/')->then('foo', function () { return 'ok'; });
        $_SERVER['REQUEST_URI'] = '/?foo=bar';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'ok');
    };

    /** When querying for an undefined state a DomainException is thrown */
    yield function () use (&$router) {
        $e = null;
        try {
            $state = $router->get('invalid');
        } catch (\DomainException $e) {
        }
        assert($e instanceof DomainException);
    };

    /** Routes can be nested using chaining */
    yield function () use (&$router) {
        $router->when('/foo/')
               ->when('/bar/')->then('foo', function () { return 'ok'; });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'ok');
    };

    /** Routes can be nested using callbacks */
    yield function () use (&$router) {
        $router->when('/foo/', function ($router) {
            $router->when('/bar/')->then('foo', function () {
                return 'ok';
            });
        });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'ok');
    };

    /** Routers can have multiple domains and URLs only match the defined domain. This works for multiple routes. */
    yield function () use (&$router) {
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
        assert($state == 'foo');
        $_SERVER['REQUEST_URI'] = '/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert(is_null($state));
        $_SERVER['HTTP_HOST'] = 'bar.com';
        $_SERVER['REQUEST_URI'] = '/bar/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'bar');
        $_SERVER['REQUEST_URI'] = '/foo/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert(is_null($state));
    };

    /** Routes can use Angular-style parameters */
    yield function () use (&$router) {
        $router->when('/:angular/')->then('foo', function ($angular) {
            return $angular;
        });
        $_SERVER['REQUEST_URI'] = '/somestring/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'somestring');
    };

    /** Routes can use braces-style parameters (e.g. Symfony) */
    yield function () use (&$router) {
        $router->when('/{braces}/')->then('foo', function ($braces) {
            return $braces;
        });
        $_SERVER['REQUEST_URI'] = '/somestring/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == 'somestring');
    };

    /** Routers can define 'fake' routes for error handling */
    yield function () use (&$router) {
        $router->when(null)->then('404', '404');
        $state = $router->get('404');
        assert($state([], ServerRequestFactory::fromGlobals()) == '404');
    };

    /**
     * When generating a route, the domain is prepended if it differs from the current domain.
     * However, if it's the same by default it is omitted.
     */
    yield function () use (&$router) {
        $router->when("http://foo.com/(?'p1':\w+)/{p2}/:p3/")
               ->then('test', function () {});
        $url = $router->generate(
            'test',
            ['p1' => 'foo', 'p2' => 'bar', 'p3' => 'baz']
        );
        assert($url == 'http://foo.com/foo/bar/baz/');
        $_SERVER['HTTP_HOST'] = 'foo.com';
        $router = new Router;
        $router->when("http://foo.com/(?'p1':\w+)/{p2}/:p3/")
               ->then('test', function () {});
        $url = $router->generate(
            'test',
            ['p1' => 'foo', 'p2' => 'bar', 'p3' => 'baz']
        );
        assert($url == '/foo/bar/baz/');
    };

    /** Routers can have a pipeline where arguments can be injected */
    yield function () use (&$router) {
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
        assert(ob_get_clean() == '12ok');
    };

    /** We can override the action on a state and inject another action */
    yield function () use (&$router) {
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
        assert(ob_get_clean() == 'barfoobar');
    };

    /** When matching a state with a default argument (regex-style), it matches either with or without that argument being passed */
    yield function () use (&$router) {
        $router->when("/(?'id'\d+/)?")->then(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state === "2");
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state === "1");
    };

    /** When matching a state with a default argument (Angular-style), it matches either with or without that argument being passed */
    yield function () use (&$router) {
        $router->when("/:id?/")->then(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state === "2");
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state == "1");
    };

    /** When matching a state with a default argument (braces-style), it matches either with or without that argument being passed */
    yield function () use (&$router) {
        $router->when("/{id}?/")->then(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state === "2");
        $_SERVER['REQUEST_URI'] = '/';
        $state = $router(ServerRequestFactory::fromGlobals());
        assert($state === "1");
    };
};

