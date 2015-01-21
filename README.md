# reroute
Flexible PHP5 HTTP router, with support for various types of URL matching,
URL arguments, custom state handling and URL generation. Reroute is designed
to be usable in any type of project or framework.

Installation
------------

###Composer (recommended)###

1. Add "monomelodies/reroute" to your `composer.json` requirements:
    ```
    {
        "require": {
            "monomelodies/reroute": ">=0.4"
        }
    }
    ```
2. Run `composer install` or `composer update`.

###Cloning with Git###

1. Clone the repository;
2. Make your project recognize Reroute:
    2.1. Register `/path/to/reroute/src` for the namespace `Reroute\\` in your
        PSR-4 autoloader (recommended);
    2.2. Alternatively, manually `include` the files you need.

###Manual download###

Same as for Git, but replace step 1. with manual download and extraction.

Defining routes
---------------

###The basics###

To match a route (a URI endpoint) to a state:

    <?php

    use Reroute\Router;
    use Reroute\Url\Flat;

    $router = new Router;
    $router->state('home', new Flat('/'), function() {
        echo 'Hello world!';
    });
    $state = $router->resolve($_SERVER['REQUEST_URI']);
    $state->run();

The second argument `$verbs` to a [Url constructor](url) defaults to
`['GET']`, since that is the most common use case in web applications.

###Matching multiple HTTP verbs###

    <?php

    $router->state('home', new Flat('/', ['GET', 'POST']), function() {
        // ...
    });

Using parameters
----------------

Of course, ReRoute supports parameters in URLs (it wouldn't be particularly
useful otherwise). In order to use parameters, you must tell your router how
to handle them by passing one of the other URL classes.

ReRoute comes with a few bundled `Url` classes:

- [Reroute\Url\Flat](url#flat), for simple URLs without parameters;
- [Reroute\Url\Regex](url#regex), for full regex matching and maximum flexibility;
- [Reroute\Url\Legacy](url#legacy), for legacy Monolyth applications;
- [Reroute\Url\Angular](url#angular), for AngularJS-style URL definitions;
- [Reroute\Url\Braces](url#braces), for {braced} parameters.

For full documentation, see [the associated pages](url); for this
readme we will use the modern [Regex handler](url#regex).

    <?php

    use Reroute\Url\Regex;

    $router->state('user', new Regex('/(\d+)/'), function($id) {
        // ...
    });

###Named parameters###

You can specify parameters with a name. The exact syntax depends on your chosen
URL class. For Regex URLs, it simply follows PHP regex syntax:

    "/(?'paramName':regex)/"

Note that the order in which they are passed to your callback is not important;
the Reroute\State figures that out for itself.

    <?php

    $router->state(
        'user',
        new Regex("/(?'firstname':\s+)/(?'lastname':\s+)/", ['GET', 'POST']),
        function($lastname, $firstname) {
            // ...
        }
    );

Resolving routes
----------------

In whatever serves as your "front controller", after state definition, attempt
to resolve it:

    <?php

    $state = $router->resolve($_SERVER['REQUEST_URI']);
    $state->run();

Generating URLs
---------------

In your views or templates, you should refrain from hardcoding URLs to states
managed by reroute, since changing a URL would involve changing ALL your views.

Instead, make sure the template has access to the $router you defined earlier.
Get a state by name and then generate a URL:

    <a href="<?=$router->get('home')->url()->generate()?>">Back to home page</a>
    <a href="<?=$router->get('user')->url()->generate(['id' => 42])?>">Go to user 42</a>

Redirecting
-----------

The same goes for redirects. Use either `Url::redirect` or `Url::move`:

    <?php

    // Redirect to home needed...
    $router->get('home')->url()->redirect();

The `redirect` method is a pretty dumb redirector; it issues a 302 header and
halts your script. For more advanced handling, you might want to throw a custom
exception and handle the redirect in a catch block, as described below.

The `move` method is similar, only with a 301 (permanent redirect) header.

Both `redirect` and `move` accept an optional argument with parameters a state
might need ($id, $name, etc.) in the same hashtable format as `Url::generate`
does.

A URL being redirected to must match the GET HTTP verb. An
`IllegalRedirectException` will be thrown otherwise.

Handling 404s and other errors
------------------------------

    <?php

    use Reroute\Url\Nomatch;

    // First, define a 404 state:
    $router->state('404', new Nomatch, function() {
        echo "The URL did an oopsie!";
    });

Since `Nomatch` will never match any URL, this is a safe placeholder. But you
could use anything, really, as long as it's not already in use in your
application.

Next, try to resolve the currently requested URI. On failure, use the 404 state
instead:

    <?php

    if ($state = $router->resolve($_SERVER['REQUEST_URI'])) {
        $state->run();
    } else {
        $router->get('404')->run();
    }

###Catching exceptions###

A best practice is to wrap your state resolution in a try/catch block, so you
can always hide exceptions from end users, throw exceptions from states etc.:

    <?php
    
    try {
        if (!($state = $router->resolve($_SERVER['REQUEST_URI']))) {
            throw new HTTP404Exception;
        }
        $state->run();
    } catch (HTTP404Exception) {
        $router->get('404')->run();
    } catch (SomeOtherException) {
        // ...handle accordingly...
        $router->get('someOtherState')->run();
    } catch (Exception) {
        // Something went REALLY unexpectedly wrong...
        $router->get('500')->run();
    }

Reroute does not come bundled with these exceptions, since it's a router and
not an HTTP library, and besides we don't want to force anyone to use _our_
custom exceptions when handling their states.
