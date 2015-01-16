<?php

use reroute\Router;

require_once realpath(__DIR__).'/../autoload.php';

$router = new Router;
$router->state('home', '/', function() {
    echo "\nHello world!\n";
});
$router->state('user', "/(?'id'\d+)/", function($id) {
    echo "\nUser $id\n";
});
$router->state(
    'order',
    "/(?'first'\w+)/(?'last'\w+)/",
    function($last, $first) {
        echo "\n$first $last\n";
    }
);
$router->resolve('/')->run();
$router->resolve('/42/')->run();
$router->resolve('/john/doe/')->run();
$router->resolve('/john/doe/?foo=bar')->run();

$_SERVER['SERVER_NAME'] = 'localhost';
echo $router->url('home')."\n";
echo $router->url('order', ['first' => 'paul', 'last' => 'smith'])."\n";

$state = $router->get('user');
try {
    $state = $router->get('invalid');
} catch (DomainException $e) {
    echo "\nNo such state as invalid.\n";
}

echo "\nAll passed!\n\n";

