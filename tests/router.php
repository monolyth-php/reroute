<?php

use Monolyth\Reroute\Router;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\RequestInterface;

return function ($test) : Generator {
    $test->beforeEach(function () use (&$router) {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Router::reset();
        $router = new Router('http://localhost');
    });

    /**We can resolve a route and it returns the desired state */
    yield function () use (&$router) {
        $router->when('/', 'foo')->get('Hello world!');
        $_SERVER['REQUEST_URI'] = '/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'Hello world!');
    };

    /** When passing unnamed parameters, they get injected */
    yield function () use (&$router) {
        $router->when("/(\d+)/")->get(function ($id) {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/1/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 1);
    };

    /** When passing named parameters, they get injected */
    yield function () use (&$router) {
        $router->when("/(?'id'\d+)/")->get(function ($id) {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/1/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 1);
    };

    /** When passing named parameters, we can inject them in any order we like */
    yield function () use (&$router) {
        $router->when("/(?'first'\w+)/(?'last'\w+)/")
               ->get(function ($last, $first) {
                    return "$first $last";
               });
        $_SERVER['REQUEST_URI'] = '/john/doe/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'john doe');
    };

    /** When injecting the current request it can be at any place in the argument list of the callback */
    yield function () use (&$router) {
        $router->when("/(?'foo'\w+)/(\w+)/")
               ->get(function ($bar, RequestInterface $request, $foo) {
                    $VERB = $request->getMethod();
                    return "$bar $VERB $foo";
               });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'bar GET foo');
    };

    /** When matching any query parameters should be ignored */
    yield function () use (&$router) {
        $router->when('/')->get(function () { return 'ok'; });
        $_SERVER['REQUEST_URI'] = '/?foo=bar';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'ok');
    };

    /** When querying for an undefined state a DomainException is thrown */
    yield function () use (&$router) {
        $e = null;
        try {
            $response = $router->get('invalid');
        } catch (\DomainException $e) {
        }
        assert($e instanceof DomainException);
    };

    /** Routes can be nested using callbacks */
    yield function () use (&$router) {
        $router->when('/foo/', null, function ($router) {
            $router->when('/bar/')->get(function () {
                return 'ok';
            });
        });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'ok');
    };

    /** Routers can have multiple domains and URLs only match the defined domain. This works for multiple routes. */
    yield function () use (&$router) {
        $router->when('http://foo.com/', null, function ($router) {
            $router->when('/foo/')->get(function () {
                return 'foo';
            });
        });
        $router->when('http://bar.com/', null, function ($router) {
            $router->when('/bar/')->get(function () {
                return 'bar';
            });
        });
        $_SERVER['HTTP_HOST'] = 'foo.com';
        $_SERVER['REQUEST_URI'] = '/foo/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'foo');
        $_SERVER['REQUEST_URI'] = '/bar/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert(is_null($response));
        $_SERVER['HTTP_HOST'] = 'bar.com';
        $_SERVER['REQUEST_URI'] = '/bar/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'bar');
        $_SERVER['REQUEST_URI'] = '/foo/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert(is_null($response));
    };

    /** Routes can use Angular-style parameters */
    yield function () use (&$router) {
        $router->when('/:angular/')->get(function ($angular) {
            return $angular;
        });
        $_SERVER['REQUEST_URI'] = '/somestring/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'somestring');
    };

    /** Routes can use braces-style parameters (e.g. Symfony) */
    yield function () use (&$router) {
        $router->when('/{braces}/')->get(function ($braces) {
            return $braces;
        });
        $_SERVER['REQUEST_URI'] = '/somestring/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == 'somestring');
    };

    /**
     * When generating a route, the domain is prepended if it differs from the current domain.
     * However, if it's the same by default it is omitted.
     */
    yield function () use (&$router) {
        $router->when("http://foo.com/(?'p1':\w+)/{p2}/:p3/", 'test')
               ->get(function () {});
        $url = $router->generate(
            'test',
            ['p1' => 'foo', 'p2' => 'bar', 'p3' => 'baz']
        );
        assert($url == 'http://foo.com/foo/bar/baz/');
        $_SERVER['HTTP_HOST'] = 'foo.com';
        $router = new Router('http://foo.com');
        $router->when("/(?'p1':\w+)/{p2}/:p3/", 'test')->get(function () {});
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
            ->get('ok');
        $_SERVER['REQUEST_URI'] = '/1/2/';
        echo $router(ServerRequestFactory::fromGlobals())->getBody();
        assert(ob_get_clean() == '12ok');
    };

    /** We can override the action on a state and inject another action */
    yield function () use (&$router) {
        ob_start();
        $router->when('/{foo}/{bar}/')
            ->get(function ($foo, $bar) {
                return $foo.$bar;
            })->post(function ($bar, callable $GET) {
                echo $bar;
                return $GET;
            });
        $_SERVER['REQUEST_URI'] = '/foo/bar/';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        echo $router(ServerRequestFactory::fromGlobals())->getBody();
        assert(ob_get_clean() == 'barfoobar');
    };

    /** When matching a state with a default argument (regex-style), it matches either with or without that argument being passed */
    yield function () use (&$router) {
        $router->when("/(?'id'\d+/)?")->get(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() === "2");
        Router::reset();
        $_SERVER['REQUEST_URI'] = '/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() === "1");
    };

    /** When matching a state with a default argument (Angular-style), it matches either with or without that argument being passed */
    yield function () use (&$router) {
        $router->when("/:id?/")->get(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() === "2");
        Router::reset();
        $_SERVER['REQUEST_URI'] = '/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() == "1");
    };

    /** When matching a state with a default argument (braces-style), it matches either with or without that argument being passed */
    yield function () use (&$router) {
        $router->when("/{id}?/")->get(function ($id = "1") {
            return $id;
        });
        $_SERVER['REQUEST_URI'] = '/2/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() === "2");
        Router::reset();
        $_SERVER['REQUEST_URI'] = '/';
        $response = $router(ServerRequestFactory::fromGlobals());
        assert($response->getBody()->__toString() === "1");
    };
};

