<?php

use Reroute\Router;
use Reroute\Url\Flat;
use Reroute\Url\Regex;
use Reroute\Url\Legacy;
use Reroute\Url\Angular;

class UrlTest extends PHPUnit_Framework_TestCase
{
    public function testGenerateFlatUrl()
    {
        $router = new Router;
        $router->state('home', new Flat('/'), function () {});
        $url = $router->get('home')->url()->generate();
        $this->assertEquals('http://localhost/', $url);
    }

    public function testGenerateRegexUrl()
    {
        $router = new Router;
        $router->state(
            'params',
            new Regex("/(?'first'\w+)/(?'last'\w+)/"),
            function ($last, $first) {}
        );
        $url = $router->get('params')->url()->generate([
            'first' => 'john',
            'last' => 'doe',
        ]);
        $this->assertEquals('http://localhost/john/doe/', $url);
    }

    public function testGenerateLegacyUrl()
    {
        $router = new Router;
        $router->state(
            'legacy',
            new Legacy("/(%s:str)/(%d:num)/(%f:flt)/(%a:all)"),
            function () {}
        );
        $url = $router->get('legacy')->url()->generate([
            'str' => 'string',
            'num' => '42',
            'flt' => '3.14',
            'all' => 'ev/ry/thing/',
        ]);
        $this->assertEquals(
            'http://localhost/string/42/3.14/ev/ry/thing/',
            $url
        );
    }

    public function testGenerateAngularUrl()
    {
        $router = new Router;
        $router->state(
            'angular',
            new Angular("/angular/:p1/:p2/"),
            function () {}
        );
        $url = $router->get('angular')->url()->generate([
            'p1' => 'is',
            'p2' => 'supported',
        ]);
        $this->assertEquals(
            'http://localhost/angular/is/supported/',
            $url
        );
    }
}

