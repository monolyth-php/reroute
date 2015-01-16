<?php

use Reroute\Router;
use Reroute\Url\Flat;
use Reroute\Url\Regex;

class UrlTest extends PHPUnit_Framework_TestCase
{
    public function testGenerateUrl()
    {
        $router = new Router;
        $router->state('home', new Flat('/'), function() {});
        $url = $router->get('home')->url()->generate();
        $this->assertEquals('http://localhost/', $url);
    }

    public function testGenerateUrlWithParameters()
    {
        $router = new Router;
        $router->state(
            'params',
            new Regex("/(?'first'\w+)/(?'last'\w+)/"),
            function($last, $first) {}
        );
        $url = $router->get('params')->url()->generate([
            'first' => 'john',
            'last' => 'doe',
        ]);
        $this->assertEquals('http://localhost/john/doe/', $url);
    }
}

