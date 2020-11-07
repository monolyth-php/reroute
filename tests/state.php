<?php

use Monolyth\Reroute\State;
use Laminas\Diactoros\{ ServerRequestFactory, Response\HtmlResponse, Response\EmptyResponse };
use Psr\Http\Message\RequestInterface;
use Laminas\Diactoros\Request;

/** Tests for states */
return function () : Generator {
    /** A state automatically and correctly resolves a HEAD request */
    yield function () {
        $state = new State('/foo');
        $state->get(function () {
            return new HtmlResponse('bar');
        });
        $result = $state([], new Request('/foo', 'HEAD'));
        assert($result instanceof EmptyResponse);
        $headers = $result->getHeaders();
        assert($headers['content-type'][0] == 'text/html; charset=utf-8');
        assert($headers['Content-Length'][0] == 3);
        $body = $result->getBody()->__toString();
        assert(strlen($body) == 0);
    };
};

