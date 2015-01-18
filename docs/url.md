# URL

Reroute matches URLs via one of the URL classes. You can extend this for your
own needs if your chosen URL placeholders aren't already provided. These custom
URL classes _must_ extend `Reroute\Url`.

## Shared API

### `Url::__construct`

Construct the object for the given `$url` and `$verbs`. The verbs default to
`['GET']`.

### `Url::prefix`

Set the prefix part of the URL.

### `Url::match`

For the given `$url` and `$method`, return an array of matched parameters on
success, or null on failure. Normally, you will not call this directly.

### `Url::generate`

Using the supplied hash of `$arguments`, generate the associated URL.

### `Url::short`

Like `Url::generate`, but takes a first argument `$current` which contains a
URL to test against. If the generated URL is on the same scheme/domain as
`$current`, that part gets stripped.

### `Url::redirect`

Using the supplied hash of `$arguments`, issue a 302 to the associated URL.

### `Url::redirect`

Using the supplied hash of `$arguments`, issue a 301 to the associated URL.

## Flat

"Flat URLs" are the simplest form of matching URLs; they're simply static
routes without any parameters:

    <?php

    $router->state('home', new Flat('/'), function() {});
    $router->state('about', new Flat('/about/', function() {});

## Regex

Regex URLs are the most flexible and low-level type of URLs. They simply
implement PHP's PCRE syntax:

    <?php

    $router->state('user', new Regex('/user/(\d+)/'), function($id) {});

Optionally, you can use named matches:

    <?php

    $router->state(
        'user',
        new Regex("/user/(?'first':\w+)/(?'last':\w+)/"),
        function($first, $last) {
        }
    );

When using named matches, the order in which you pass them to your callback
is not important. The [`State`](state) will figure it out.

## Legacy

Legacy URLs support the format previous versions of Monolyth used. You
shouldn't really be using these anymore, they're mostly supplied for
convenience.

A match to a "string" (slug) would e.g. look as follows:

    <?php

    $router->state(
        'legacy',
        new Legacy('/(%s:name)/'),
        function($name) {
        }
    );

Legacy URLs must be named and support these types:

- `%s`: a slug `([a-z0-9-]+)`;
- `%d`: an integer `(\d+)`;
- `%f`: a float `(\d+\.\d+)`;
- `%a`: everything `(.*)`;

## Angular

AngularJS-style URL matching. Currently this doesn't _fully_ support all
options, just the simple syntax:

    <?php

    $router->state(
        'angular',
        new Angular('/:name/'),
        function($name) {
        }
    );

The order of the arguments in the callback is not important.

## Braces

Braced-style URLs a lot of frameworks use:

    <?php

    $router->state(
        'braces',
        new Braces('/{name}/'),
        function($name) {
        }
    );

The order of the arguments in the callback is not important.
