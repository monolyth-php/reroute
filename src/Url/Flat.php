<?php

namespace Reroute\Url;

use Reroute\Url;

class Flat extends Url
{
    public function match($url)
    {
        if ($this->full($this->url) == $this->full($url)) {
            return [];
        }
        return null;
    }

    public function generate(array $arguments = [])
    {
        return $this->full($this->url);
    }
}

