# State

The callable defined in a route is wrapped in a `State` object. Normally, you
should not instantiate one directly.

## `State::__construct`

States get constructed using the URL associated with them, and the callback.

## `State::group`

Get or set the group (namespace) this State is in.

## `State::match`

For the given `$url` and `$method`, return `true` or `false` if this state
matches it or not.

## `State::run`

Run the callback associated with this state. Note that callbacks will be called
until they stop returning a callable, so you can easily chain these. E.g., for
defining a state with a controller from some other framework:

    <?php

    $router->state('test', new Flat('/test/'), function() {
        $controller = new \My\Other\Framework\Controller;
        return [$controller, 'actionTest'];
    });

## `State::verb`

Returns the HTTP verb this state was matched against, or null for non-matched
states (e.g. when using `Router::get`).

## `State::url`

Return the `URL` object this state matches for.
