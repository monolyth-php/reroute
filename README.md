# Reroute
Flexible PHP5 HTTP router, with support for various types of URL matching,
URL arguments, custom state handling and URL generation. Reroute is designed
to be usable in any type of project or framework.

## Installation

### Composer (recommended)

Add "monomelodies/reroute" to your `composer.json` requirements:

    {
        "require": {
            "monomelodies/reroute": ">=0.4"
        }
    }

### Manual installation

1. Get the code;
    1.1. Clone the repository, e.g. from GitHub;
    1.2. Download the ZIP (e.g. from Github) and extract.
2. Make your project recognize Reroute:
    2.1. Register `/path/to/reroute/src` for the namespace `Reroute\\` in your
        PSR-4 autoloader (recommended);
    2.2. Alternatively, manually `include` the files you need.

## Defining routes

### The basics

Define a route:

    <?php

    $router = new Router;
    $router->state('example', new Flat('/example/'), function() {
        echo 'Hello world!';
    });

To match a route (a URI endpoint) to a state:

    <?php

    $state = $router->resolve('/example/');
    $state->run();

### Matching multiple HTTP verbs

The special `$VERB` parameter gets filled with the HTTP verb used for this
resolved request.

    <?php

    $router->state('home', new Flat('/', ['GET', 'POST']), function($VERB) {
        if ($VERB == 'GET') {
            // ...
        } elseif ($VERB == 'POST') {
            // ...
        }
    });

## Using parameters

Use one of the URL types (other than `Flat` and `Nomatch`) to match parameters
in a URL, for instance:

    <?php

    $router->state('user', new Regex('/(\d+)/'), function($id) {
        echo "User $id";
    });

### Named parameters

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

## Generating URLs

Make sure your view or template has access to the $router you defined earlier.
Get a state by name and then generate a URL:

    <?php

    echo 'URL to home page: '.$router->get('home')->url()->generate();
    echo 'URL to user #42: '.$router->get('user')->url()->generate(['id' => 42]);

## Redirecting

Reroute URLs support simple `301` or `302` redirects:

    <?php

    // Temporarily redirect to state "home":
    $router->get('home')->url()->redirect();
    // Permanently redirect to state "example":
    $router->get('example')->url()->move();

A URL being redirected to must match the GET HTTP verb associated with the
state. An `IllegalRedirectException` will be thrown otherwise.

## Handling 404s and other errors

    <?php

    $router->state('404', new Nomatch, function() {
        echo "The URL did an oopsie!";
    });
    if ($state = $router->resolve($_SERVER['REQUEST_URI'])) {
        $state->run();
    } else {
        $router->get('404')->run();
    }

A best practice is to wrap your state resolving in a `try/catch` block, and
handle any error accordingly.
