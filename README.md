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

### `when`? `then`!
Since the Reroute router responds to HTTP requests, we use the `when` and `then`
methods to respond:

```php
<?php

use Monolyth\Reroute\Router;

$router = new Router;
$router->when('/some/url/')->then(function () {
    // Return something.
});
```

`when` starts matching whenever it can, so if your project lives under (for
example) `http://my-url.com/bla/my-framework/libs` the example route above could
match `/bla/my-framework/libs/some/url/` if nothing better was defined.

> Note that Reroute matches _parts_ of URLs, hence the fact that your defined
> route starts with `/` doesn't have any special meaning.

`when` returns a new Router with the specified URL as its "base" (the first
constructor argument). For nested routers (see below), this includes the base
for _all_ parent routers. Schematically:

```php
<?php

use Monolyth\Reroute\Router;

$router = new Router;
$foo = $router->when('/foo/');
$bar = $foo->when('/bar/');
$baz = $bar->when('/baz/')->then('I match /foo/bar/baz/!');
```

What `then` returns can be really anything. If you pass a callable, that in turn
should eventually return something non-callable. Hence, the following four forms
are equivalent:

```php
<?php

$router->when('/some/url/')->then(function () {
    return 'Hello world!';
});;
$router->when('/some/url/')->then('Hello world!');

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

$router->when('/some/url/')->then(new Foo);
$router->when('/some/url/')->then(['Foo', 'getInstance']);
```

### Named states
When called with two parameters, the first parameter is assumed to be the
(preferably unique) name of the state. Named states can be retrieved at any
point by calling `get('name_of_state')` on the router:

```
<?php

$router->when('/the/url/')->then('myname', 'handler');
$state = $router->get('myname'); // Ok!
$state instanceof Reroute\State; // true
```

### Resolving a request
After routes are defined, somewhere in your front controller you'll want to
actually resolve the request:

```php
<?php

use Zend\Diactoros\ServerRequestFactory;

if ($state = $router(ServerRequestFactory::fromGlobals())) {
    echo $state;
} else {
    // 404!
}
```

(Note that you don't need to explicitly pass in a `ServerRequest` object, the
router uses the current request by default.)

Invoking the router starts a [pipeline](https://github.com/thephpleague/pipeline).
By calling the router's `pipe` method you can add middleware to the stack.

If a valid state was found for the current URL, it's return value is returned by
the pipeline. Otherwise, it will resolve to `null`.

> To emulate a different request type than the actual one, simply change
>`$_SERVER['REQUEST_METHOD']`.

## Passing parameters
Your URLs are actually regexes, so you can match variables to pass into the
callback:

```php
<?php

$router->when("/(?'name'\w+)/")->then(function ($name) {
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
match an integer. PHP 7 supports extended type hinting in callables, so this
will be further improved in a future release.

## Inspecting the current request
By type hinting a parameter as an instance of
`Psr\Http\Message\RequestInterface`, you can inject the original request object
and check the used method (or anything else of course):

```php
<?php

use Psr\Http\Message\RequestInterface;

$router->when('/some/url/')->then(function (RequestInterface $request) {
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

## Limiting to verbs (or extending the palet)
The default behaviour is to match `GET` and `POST` actions only since they are
most common in web applications. Normally a `POST` to a static page should act
like a `GET`. However, one can specifically instruct certain URLs to respond to
certain methods:

```php
<?php

use Zend\Diactoros\Response\EmptyResponse;

$router->when('/some/url/')->then('my-awesome-state', function () {
    // Get not allowed!
    return new EmptyResponse(403);
})->post(function () {
    // ...do something, POST is allowed...
    // Since we disabled get, this should redirect somewhere valid afterwards.
});
```

Available verb methods are `post`, `put`, `delete`, `head` and `options`.
Subsequent calls extend the current state, and any existing actions are
overridden on re-declaration.

### Referring to other callbacks
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
The optional second argument to `when` is a callable, which expects a single
parameter: a new (sub) router. All routes defined using `when` on the subrouter
will inherit the parent router's URL:

```php
<?php

$router->when('/foo/', function ($router) {
    $router->then('I match /foo/!');
    $router->when('/bar/')->then('I match /foo/bar/!');
});
```

Since `when` also returns the new subrouter, you can also use one of the
following patterns if you prefer:

```php
<?php

$router->when('/foo/')->when('/bar/')->then('I match /foo/bar/!');
// ...or...
$foo = $router->when('/foo/');
$foo->when('/bar/')->then('I match /foo/bar/!');
```

For convenient chaining, `then` returns the (sub)router itself:
```php
<?php

$router->when('/foo/')
       ->then('I match /foo/!')
       ->when('/bar/')
       ->then('But I match /foo/bar/!');
```

## Pipelining middleware
Since routes are pipelined, you can at any point add one or more calls to the
`pipe` method to add middleware:

```php
<?php

$router->when('/restricted/')
    ->pipe(function ($payload) {
        if (!user_is_authenticated()) {
            // In the real world, probably raise an exception you can
            // catch elsewhere and show a login page or something...
            return null;
        }
        return $payload;
    })
    ->when('/super-secret-page/')
    ->then('For authenticated eyes only!');
```

You can call `pipe` as often as you want. Subrouters won't be executed if the
pipeline is short-circuited anywhere.

When using named parameters, the pipelined callable can optionally specify which
parameters it also wants to use:

```php
<?php

$router->when("/(?'foo':\d+)/")
    ->pipe(function ($payload, $foo) {
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

## Generating URLs
To generate a URL for a defined named state, use the `generate` method:

```php
<?php

$router->when('/:some/:params/')->then('myname', 'handler');
echo $router->generate('myname', ['some' => 'foo', 'params' => 'bar']);
// outputs: /foo/bar/
```

The optional third parameter to generate is a boolean telling `generate` if it
should prefer a route without scheme/host if the user is already on the current
host. It defaults to true. The above example might output
`http://localhost/foo/bar/` if called with `false` as the third parameter.

Generation is only possible for named states, since anonymous ones obviously
could only be retrieved by their actual URL (in which case you might as well
hardcode it...). Use named states if your URLs are likely to change over time!

## Handling 404s and other errors
```php
<?php

$router->when(null)->then('404', function() {
    return "The URL did an oopsie!";
});

```

By passing `null` as a URL, something random is generated interally that won't
normally match anything actual in the routing table. Hence, this is a safe
placeholder. But you could use anything, really, as long as it's not already in
use in your application.

Next, try to resolve the currently requested URI. On failure, use the 404 state
instead:

```php
<?php

if ($state = $router()) {
    echo $state;
} else {
    // Note that we must "invoke" the state.
    echo $router->get('404')();
}

```

A best practice is to wrap your state resolving in a `try/catch` block, and
handle any error accordingly so views/controllers/etc. can throw exceptions.

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

