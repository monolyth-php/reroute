<?php

namespace Reroute\Url;

use Reroute\Url;
use BadMethodCallException;

class Nomatch extends Url
{
    /**
     * Check if this URL is a match.
     *
     * @param string $url The requested URL.
     * @param string $method The HTTP method (verb).
     */
    public function match($url, $method)
    {
        // By design, Nomatch should never match...
        return null;
    }

    public function generate(array $arguments = [])
    {
        throw new BadMethodCallException;
    }
}

