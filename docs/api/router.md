# Router

Use an instance of the `Router` class to define your states somewhere in your
front controller:

    <?php

    use Reroute\Router;
    use Reroute\Flat;
    use Reroute\Regex;

    $router = new Router;
    $router->state('home', new Flat('/'), function() {
    });
    $router->state('user', new Regex('/(\d+)/'), function($id) {
    });

    if ($state = $router->resolve(
        $_SERVER['REQUEST_URI'],
        $_SERVER['REQUEST_METHOD']
    )) {
        $state->run();
    } else {
        echo "404...";
    }

## `Router::state`

Register a state under a URL.

    <?php

    $router->state($statename, Reroute\Url $url, callable $callback);

`$callback` contains code to run if a state was succesfully resolved.

## `Router::under`

You may group states matching a prefix using `Router::under`:

    <?php

    $router->under('/account', function ($router) {
        $router->state('email', new Flat('/email/'), function () {
            // ...edit email...
        });
        $router->state('pass', new Flat('/password/'), function () {
            // ...edit password...
        });
    });

## `Router::group`

You may namespace states belonging to a category using `Router::group`. The
group name is arbitrary, but you can use it to handle resolved states later:

    <?php

    $router->group('must-be-logged-in', function($router) {
        $router->state('email', new Flat('/email/'), function () {
            // ...edit email...
        });
        $router->state('pass', new Flat('/password/'), function () {
            // ...edit password...
        });
    });
    if ($state = $router->resolve($_SERVER['REQUEST_URI']) {
        if ($state->group() == 'must-be-logged-in'
            && !isset($_SESSION['user'])
        ) {
            echo "No access.";
        } else {
            $state->run();
        }
    });

## `Router::resolve`

Resolve the requested URL. This example assumes an HTTP request using the
Apache webserver, but other types/servers should work similarly:

    <?php

    $state = $router->resolve(
        $_SERVER['REQUEST_URI'],
        $_SERVER['REQUEST_METHOD']
    );

Note that any `GET` query parameters are _not_ matched.

## `Router::get`

Returns the state with the request name, or throws a `DomainException` if no
such state exists.
