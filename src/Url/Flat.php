<?php

namespace Reroute\Url;

use Reroute\Url;

class Flat extends Url
{
    public function match($url, $method)
    {
        if ($this->full($this->url) == $this->full($url)
            && in_array($method, $this->verbs)
        ) {
            return [];
        }
        return null;
    }

    public function generate(array $arguments = [])
    {
        return $this->full($this->url);
    }
}

