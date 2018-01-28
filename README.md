# Reroute
Flexible PHP5 HTTP router, with support for various types of URL matching,
URL arguments, custom state handling and URL generation. Reroute is designed
to be usable in any type of project or framework.

- [Homepage](http://monolyth.monomelodies.nl/reroute/)
- [Documentation](http://monolyth.monomelodies.nl/reroute/docs/)

## Installation

### Composer (recommended)

```bash
$ composer require monolyth/reroute
```

### Manual installation

1. Get the code;
    1. Clone the repository, e.g. from GitHub;
    2. Download the ZIP (e.g. from Github) and extract.
2. Make your project recognize Reroute:
    1. Register `/path/to/reroute/src` for the namespace `Monolyth\\Reroute\\`
       in your PSR-4 autoloader (recommended);
    2. Alternatively, manually `include` the files you need.

## Basic Usage

### Responding to requested URLs
Since the Reroute router responds to HTTP requests, we use the `when` method to
define a valid URL:

```php
<?php

use Monolyth\Reroute\Router;

$router = new Router('http://example.com');
$state = $router->when('/some/url/', 'some-state');
```

`when` returns a new `State` as a response to the specified URL. You must then
define the _HTTP verbs_ to which that state will respond, and with what:

```php
<?php

$state->get('Hello world!');
$state->post(new Zend\Diactoros\Response\EmptyResponse(500));
```

The HTTP verb methods currently supported are `get`, `post`, `put`, `delete`,
`head` and `options`. There is also the special `any` method which covers them
all with one single reponse.

A response can really be anything:

1. If it is a callable, it is called (with the arguments extracted from the URL)
   until it is no longer callable.
2. If it is a string _and_ a class exists by that name, it is an instance of
   that class.
3. If the end result is _not_ an instance of
   `Psr\Http\Message\ResponseInterface`, it is wrapped in a
   `Zend\Diactoros\Response\HtmlResponse`.

Hence, the following forms are equivalent:

```php
<?php

use Zend\Diactoros\Response\HtmlResponse;

$router->when('/some/url/')->get(function () {
    return 'Hello world!';
});
$router->when('/some/url/')->get('Hello world!');
$router->when('/some/url/')->get(function () {
    return new HtmlResponse('Hello world!');
});

class Foo
{
    public static function getInstance()
    {
        return new Foo;
    }

    public function __invoke()
    {
        return 'Hello world!';
    }
}

$router->when('/some/url/')->get(new Foo);
$router->when('/some/url/')->get(Foo::class);
$router->when('/some/url/')->get(['Foo', 'getInstance']);
```

## Named states
The second parameter to `when` is the (hopefully unique!) name of the state.
This can be used later on when generating routes (see below). If no name is
required it may be `null` (also, the default). You can also use `Router::get` to
retrieve a particalar state by name later on (e.g. maybe your routing is
insanely complex and split over multiple files).

```
<?php

$router->when('/the/url/', 'myname')->get('handler');
$state = $router->get('myname'); // Ok!
$state instanceof Monolyth\Reroute\State; // true
```

## Resolving a request
After routes are defined, somewhere in your front controller you'll want to
actually resolve the request. The `ResponseInterface` object can then be
emitted, e.g. using Zend Diactoros (which is bundled, but you could emit it any
way you like):

```php
<?php

use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Response\SapiEmitter;

if ($response = $router(ServerRequestFactory::fromGlobals())) {
    $emitted = new SapiEmitter;
    $emitter->emit($response);
} else {
    // 404!
}
```

(Note that you don't need to explicitly pass in a `ServerRequest` object, the
router uses the current request by default. But if you use something else than
Diactoros, it is possible to override this.)

Invoking the router starts a [pipeline](https://github.com/thephpleague/pipeline).
By calling the router's `pipe` method you can add middleware to the stack.

If a valid state was found for the current URL, it's return value is returned by
the pipeline. Otherwise, it will resolve to `null`.

> To emulate a different request type than the actual one, simply change
> `$_SERVER['REQUEST_METHOD']`.

## Passing parameters
Your URLs are actually regexes, so you can match variables to pass into the
callback:

```php
<?php

$router->when("/(?'name'\w+)/")->get(function (string $name) {
    return "Hi there, $name!";
});
```

Variables can be _named_ (in which case the order you pass them to your callback
doesn't matter - Reroute does reflection on the callable to determine the best
fit) or _anonymous_ (in which case they'll be passed in order).

### Shorthand placeholders
For simpler URLs, you can also use a few shorthand placeholders. The following
three statements are identical:

```php
<?php

$router->when("/(?'param'.*?)/");
$router->when('/:param/');
$router->when('/{param}/');
```

When using placeholders, note that one has less control over parameter types.
Using regexes is more powerful since you can force e.g. `"/(?'id'\d+)/"` to
match an integer and even type-hint it in the callable.

## Inspecting the current request
By type hinting a parameter as an instance of
`Psr\Http\Message\RequestInterface`, you can inject the original request object
and inspect the used method (or anything else of course):

```php
<?php

use Psr\Http\Message\RequestInterface;

$router->when('/some/url/')->any(function (RequestInterface $request) {
    switch ($request->getMethod()) {
        case 'POST':
            // Perform some action
        case 'GET':
            return 'ok';
        default:
            return $request->getMethod()." method not allowed.";
    }
});
```

## Referring to other callbacks
A parameter typehinted as `callable` matching a defined action (in uppercase)
can be used to "chain" to another action. So the following pattern is common for
URLs requiring special handling on e.g. a `POST`:

```php
<?php

$router->when('/some/url/')->then('my-state', function() {
    return 'This is a normal page';
})->post(function (callable $GET) {
    // Perform some action...
    return $GET;
});
```

Note there is no need to re-pass any URL parameters to the callable; they are
injected automatically. Hence, calls to `get` and `post` etc. may
accept/recognize different parameters in different orders.

> Custom verb callbacks do _not_ "bubble up" the routing chain. Hence,
> specifically disabling `POST` on `/foo/` does not affect the default
> behaviour for `/foo/bar/`.

If the injected action is not available for this state, a 405 error is
returned instead.
        
## Grouping
The optional third argument to `when` is a callable, which expects a single
parameter: a new (sub) router. All routes defined using `when` on the subrouter
will inherit the parent router's URL:

```php
<?php

$router->when('/foo/', null, function ($router) {
    $router->when('/bar/')->get('I match /foo/bar/!');
})->get('I match /foo/!);
```

The result of a grouped `when` call is itself a state, which may be piped and/or
resolved. For convenience, this state can also be defined _inside_ the callback
using `->when('/', ...)`. So, instead of this (which is itself perfectly valid):

```php
<?php

$router->when('/', 'home', function ($router) {
    // Looooong list of subroutes under /...
})->pipe($somePipe)->get('Home!');
```

...you may also write the (more readable):

```php
<?php

$router->when('/', null, function ($router) {
    $router->when('/', 'home')->get('Home!');
    // Looooong list of subroutes under /...
})->pipe($somePipe);
```

## Pipelining middleware
Since states are pipelined, you can at any point add one or more calls to the
`pipe` method to add middleware:

```php
<?php

$router->when('/restricted/')
    ->pipe(function ($payload) {
        if (!user_is_authenticated()) {
            return new RedirectResponse('/login/');
        }
        return $payload;
    })
    ->get('For authenticated eyes only!');
```

You can call `pipe` as often as you want. Subrouters won't be executed if the
pipeline is short-circuited anywhere.

When using named parameters, the pipelined callable can optionally specify which
parameters it also wants to use:

```php
<?php

$router->when("/(?'foo':\d+)/")
    ->pipe(function ($payload, int $foo) {
        if ($foo != 42) {
            // return error response or something...
        }
        return $payload;
    });
```

This is similar to the state resolving callable, except that there is _always_
a first parameter `$payload`, and injecting the `$request` isn't possible.

One common use of this is defining a pipe for a first `$language` parameter in
a group of routes, and setting some environment variable to its value for all
underlying routes.

`$payload` is, by definition, an instance of
`Psr\Http\Message\RequestInterface`. As soon as any pipe returns an instance of
`Psr\Http\Message\ResponseInterface`, everything is halted and it is designated
as the chosen response for this route in its current state. One common use for
this is to redirect users if they are trying to access page A, but need to do
something on page B first (e.g. login).

## Generating URLs
To generate a URL for a defined named state, use the `generate` method:

```php
<?php

$router->when('/:some/:params/', 'myname')->get('handler');
echo $router->generate('myname', ['some' => 'foo', 'params' => 'bar']);
// outputs: /foo/bar/
```

The optional third parameter to generate is a boolean telling `generate` if it
should prefer a route without scheme/host if the user is already on the current
host. It defaults to true. The above example might output
`http://localhost/foo/bar/` if called with `false` as the third parameter. This
is useful if the generated routes are to be used outside your application, e.g.
in an email sent out.

Generation is only possible for named states, since anonymous ones obviously
could only be retrieved by their actual URL (in which case you might as well
hardcode it...). Use named states if your URLs are likely to change over time!

### Cascading arguments
When generating a route in a subrouter, all named arguments set in the parent
router are automagically injected into the passed arguments.

An example:

```php
<?php

use Zend\Diactoros\Response\RedirectResponse;

$router->when("/(?'language'[a-z]{2})/", null, function ($router) {
    $router->when('/', 'home')->get(function () { /* some page */ });
    $router->when('/home/')->get(function () use ($router) {
        return new RedirectResponse($router->generate('home'));
    });
});

// Now, assuming we navigate to `/en/home/` we get redirected to `/en/`!

```

Of course, you could also inject `string $langauge` as a parameter in the
sub-route, but this gets tiresome.

> Note that arguments passed in the `generate` call's second argument receive
> precedence over previously matched ones. This means you could explicitly
> redirect `/en/home/` to `/nl/` in the above example by doing:
> `$router->generate('home', ['language' => 'nl']);`.

## Handling 404s and other errors
The result of `$router()` will be `null` if no match was found, so that means
you need to show a 404 error page. Beste practice is to wrap that call in a
`try/catch` block and throw exceptions on errors. That way you can show a
generic 500 error page in the `catch` block (and maybe do some logging).

## Routes with default arguments
In some situations it comes in handy to be able to specify a "default argument"
in your callback. E.g., when a call to `/user/` should show the currently
logged in user's profile, and a call to `/user/:id/` that of the specified user.

This is possible in Reroute by making the argument optional in the URL and
giving a default value in the callback. For the above example, one could e.g.
do:

```php
<?php

$router->when("/user/(?'id'\d+/)?")->then(function ($id = null) {
    if (!isset($id)) {
        $id = $GLOBALS['user']->id;
    }
    // ...return profile for $id...
    return "<h1>User profile for $id</h1>";
});

```

The shorthand URL matching style can be made optional by postfixing the
placeholder with a question mark:

```php
<?php

$router->when('/user/:id?/');
$router->when('/user/{id}?/');

```

Note that any argument found ending in a slash has this stripped, since normally
slashes are reserved for argument separation.

