<?php

namespace Reroute\Url;

use Reroute\Url;

class Angular extends Url
{
    /**
     * Check if this URL is a match.
     *
     * @param string $url The requested URL.
     * @param string $method The HTTP method (verb).
     */
    public function match($url, $method)
    {
        $newurl = preg_replace('@:(\w+)@', '(\w+)', $this->url);
        if (preg_match("@^$newurl$@", $url, $matches)) {
            unset($matches[0]);
            foreach ($matches as $key => $value) {
                if (is_numeric($key)) {
                    unset($matches[$key]);
                }
            }
            return $matches;
        }
        return null;
    }

    /**
     * Generate a URL.
     */
    public function generate(array $arguments = [])
    {
        return $this->full(preg_replace_callback(
            "@:(\w+)@",
            function ($match) use ($arguments) {
                return $arguments[$match[1]];
            },
            $this->url
        ));
    }
}

