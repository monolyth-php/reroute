# reroute
Flexible PHP5 HTTP router

Installation
------------

1. Clone the repository
2. Include the autoloader: `<?php include '/path/to/reroute/autoload.php' ?>`
3. Done! Start using it :)

###Installation using Monolyth###

1. Add a resource and version to Lintels.json
2. Run /path/to/monolyth/bin/modules update /path/to/site

Defining routes
---------------

###The basics###

To match a route (a URI endpoint) to a state:

    <?php

    use reroute\Router;

    $router = new Router;
    $router->state('home', '/:GET', function() {
        echo 'Hello world!';
    });
    $state = $router->resolve($_SERVER['REQUEST_URI']);
    $state->run();

The value GET for an HTTP verb may be omitted, since that is the most common
use case in web applications.

###Matching multiple HTTP verbs###

    <?php

    $router->state('home', '/:(GET|POST)', function($verb) {
        // ...
    });

###Using parameters###

    <?php

    $router->state('user', '/(\d+)/', function($id) {
        // ...
    });

###Using named parameters###

Note that the order in which they are passed to your callback is not important;
the reroute\State figures that out for itself.

    <?php

    $router->state(
        'user',
        "/(?'firstname':\s+)/(?'lastname':\s+)/:(?'verb':(GET|POST))",
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

Redirecting
-----------

The same goes for redirects. Use the Router::goto method:

    <?php

    // Redirect to home needed...
    $router->goto('home');

The goto method is a pretty dumb redirector; it issues a 302 header and halts
your script. For more advanced handling, you might want to throw a custom
exception and handle the redirect in a catch block, as described below.

For convenience, the router also offers a moveto method which does the same,
only with a 301 header (permanent redirect) instead.

Both goto and moveto accept optional arguments a state might need ($id, $name,
etc.).

By design, only GET states can be redirected to, since one cannot redirect a
POST anyway. A common use (and in fact best practice) would be to redirect to
a GET state _after_ a POST was handled, to avoid double posting.

Handling 404s and other errors
------------------------------

    <?php

    // First, define a 404 state:
    $router->state('404', ':', function() {
        echo "The URL did an oopsie!";
    });

Since ':' will never match any URL, this is a safe placeholder. But you could
use anything, really, as long as it's not already in use in your application.

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
    
    use reroute\HTTP404Exception;

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
