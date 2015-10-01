# Reroute
Flexible PHP5 HTTP router, with support for various types of URL matching,
URL arguments, custom state handling and URL generation. Reroute is designed
to be usable in any type of project or framework.

- [Homepage](http://reroute.monomelodies.nl)
- [Documentation](http://reroute.monomelodies.nl/docs/)

## Installation

### Composer (recommended)

```bash
composer require monomelodies/reroute
```

### Manual installation

1. Get the code;
    1. Clone the repository, e.g. from GitHub;
    2. Download the ZIP (e.g. from Github) and extract.
2. Make your project recognize Reroute:
    1. Register `/path/to/reroute/src` for the namespace `Reroute\\` in your
       PSR-4 autoloader (recommended);
    2. Alternatively, manually `include` the files you need.

## Usage

### The basics
Since the Reroute router responds to HTTP requests, we use the `when` and `then`
methods to respond:

```php
<?php

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

What `then` returns can be really anything. If you pass a callable, that in turn
should eventually return a string or something `__toString`able. Hence, the
following four forms are equivalent:

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

    public function __toString()
    {
        return 'Hello world!';
    }
}
$router->when('/some/url/')->then(new Foo);
$router->when('/some/url/')->then(['Foo', 'getInstance']);
```

### Matching HTTP verbs
The special `$VERB` parameter can be injected into the callback defined by
`then`:

```php
<?php

$router->when('/some/url/')->then(function ($VERB) {
    switch ($VERB) {
        case 'POST':
            // Perform some action
        case 'GET':
            return 'ok';
        default:
            return "$VERB method not allowed.";
    }
});
```

### Resolving a request
After routes are defined, somewhere in your front controller you'll want to
actually resolve the request:

```php
<?php

if ($state = $router->resolve($_SERVER['REQUEST_URI'])) {
    echo $state;
} else {
    // 404!
}
```

`resolve` returns a State object (if a match was found, otherwise `null`) which
offers a `run` method. This method actually invokes the defined callable
(optionally forwarding calls if the callable's return value is itself callable)
and should eventually return something to output.

### Passing parameters
Your URLs are actually regexes, so you can defined variables to pass into the
callback:

```
<?php

$router->when("/('name'\w+)/")->then(function ($name) {
    return "Hi there, $name!";
});
```

The order is not important, the Router will figure that out for you.

### Short-circuiting
Often it's handy to short-circuit a path part, e.g. because an authentication
check failed. The optional second argument to `when` can be a callback, which
gets passed the same optional matched parameters as `then` would. If the
callback exists and returns `false` (note: not `null`, strict matching here!)
the resolve is short-circuited. Of course, you could also throw an exception if
that's more your style and catch it elsewhere:

```
<?php

$router->when('/account/', function () {
    // Check authentication...
    // Did it fail?
    return false;
})->when('/profile/')->then('Only if logged in');
```

The route `/account/profile/` will never be matched if the authentication
failed.

### Grouping
The previous example also illustrated the chainability of routes: each call to
`when` returns a sub-router. If you have many paths under a base path (e.g.
`/profile/`, `/password/`, `/email/` etc. under `/account/`) there are two ways
to group those routes without having to duplicate `/account/` all the time:

```
<?php

// Method 1: use an intermediate variable:
$account = $router->when('/account/', function () { /* check */ });
$account->when('/profile/')->then('profile');
$account->when('/password/')->then('password');
// etc.

// Method 2: use the optional third callback argument to `when`:
$router->when('/account/', function () { /* check */ }, function ($router) {
    $router->when('/profile/')->then('profile');
    $router->when('/password/')->then('password');
    // etc.
});
```

If your grouping doesn't need any intermediate check, you can also simply leave
it out and the Router will figure it out:

```
<?php

$router->when('/grouped/', function ($router) {
    // ...
});
```

### Shorthand placeholders
For simpler URLs, you can also use a few shorthand placeholders:

```
<?php

$router->when("/('param'.*?)/");
$router->when('/:param/');
$router->when('/{param}/');
```

### Named states
An extension of `when`, a named state can referred to by its name (duh). This is
mostly useful if you later plan to generate a URL for a state in a view
template:

```
<?php

$router->state('myname', '/the/url/')->then('handler');
$myname = $router->get('myname'); // Ok!
```

### Generating URLs
Speaking of which, to generate a URL for a defined named state, use the
`generate` method:

```
<?php

$router->state('myname', '/:some/:params/')->then('handler');
echo $router->generate('myname', ['some' => 'foo', 'params' => 'bar']);
// outputs: /foo/bar/
```

The optional third parameter to generate is a boolean telling `generate` if it
should prefer a route without scheme/host if the user is already on the current
host. It defaults to true. The above example might output
`http://localhost/foo/bar/` (`localhost` is used if nothing else was defined
explicitly).

Generation is only possible for named states, since anonymous ones obviously
could only be retrieved by their actual URL (in which case you might as well
hardcode it...). Use named states if your URLs are likely to change over time!

## Redirecting

Reroute URLs support simple `301` or `302` redirects:

```php
<?php

// Temporarily redirect to state "home":
$router->redirect('home');
// Permanently redirect to state "example":
$router->move('example');

```

Optional second parameter is a hash of arguments as in `generate`.
Note that if the Router detects the state being redirected to is already the
current URL, nothing happens unless a third parameter `$force` is set to true.
This could be useful when redirecting after a succesfull `POST` to prevent
double-posting.

## Handling 404s and other errors

```
<?php

$router->state('404', null, function() {
    echo "The URL did an oopsie!";
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

if ($state = $router->resolve($_SERVER['REQUEST_URI'])) {
    echo $state;
} else {
    echo $router->get('404');
}

```

A best practice is to wrap your state resolving in a `try/catch` block, and
handle any error accordingly so views/controllers/etc. can throw exceptions.

