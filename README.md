# reroute
Flexible PHP5 HTTP router

Installation
------------

1. Clone the repository
2. Include the autoloader: `<?php include '/path/to/reroute/autoload.php' ?>`
3. Done! Start using it :)

###Installation using Composer###

1. Add a resource and version to Lintels.json
2. Run /path/to/monolyth/bin/modules update /path/to/site

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

The second argument $verbs to a Url constructor defaults to ['GET'], since
that is the most common use case in web applications.

###Matching multiple HTTP verbs###

    <?php

    $router->state('home', new Flat('/', ['GET', 'POST']), function($verb) {
        // ...
    });

Using parameters
----------------

Of course, ReRoute supports parameters in URLs (it wouldn't be particularly
useful otherwise). In order to use parameters, you must tell your router how
to handle them by passing one of the other URL classes.

ReRoute comes with a few bundled Url classes:

- Reroute\Url\Flat, for simple URLs without parameters;
- Reroute\Url\Regex, for full regex matching and maximum flexibility;
- Reroute\Url\Legacy, for legacy Monolyth applications;
- Reroute\Url\Angular, for AngularJS-style URL definitions;

For full documentation, see the associated pages; for this readme we will
use the modern Regex handler.

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
the reroute\State figures that out for itself.

    <?php

    $router->state(
        'user',
        new Regex("/(?'firstname':\s+)/(?'lastname':\s+)/", ['GET', 'POST']),
        function($verb, $lastname, $firstname) {
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

Instead, make sure the template has access to the $router you defined earlier
and call the Router::url method:

    <a href="<?=$router->url('home')?>">Back to home page</a>
    <a href="<?=$router->url('user', ['id' => 42])?>">Go to user 42</a>

Redirecting
-----------

The same goes for redirects. Use the Router::redirect method:

    <?php

    // Redirect to home needed...
    $router->redirect('home');

The goto method is a pretty dumb redirector; it issues a 302 header and halts
your script. For more advanced handling, you might want to throw a custom
exception and handle the redirect in a catch block, as described below.

For convenience, the router also offers a move method which does the same,
only with a 301 header (permanent redirect) instead.

Both goto and moveto accept optional arguments a state might need ($id, $name,
etc.).

By design, only GET states can be redirected to, since one cannot redirect a
POST anyway. A common use (and in fact best practice) would be to redirect to
a GET state _after_ a POST was handled, to avoid double posting.

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

Next, try to resolve the currently request URI. On failure, use the 404 state
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
    
    use Reroute\HTTP404Exception;

    try {
        if (!($state = $router->resolve($_SERVER['REQUEST_URI']))) {
            throw new HTTP404Exception;
        }
        $state->run();
    } catch (HTTP404Exception) {
        $router->get('404')->run();
    } catch (SomeOtherException) {
        // ...handle accordingly...
    } catch (Exception) {
        // Something went REALLY unexpectedly wrong...
    }

For convenience, reroute comes packaged with HTTPxxxExceptions for most common
responses.
