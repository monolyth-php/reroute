<?php

namespace Reroute\Url;

use Reroute\Url;

class Legacy extends Url
{
    /**
     * Check if this URL is a match.
     *
     * @param string $url The requested URL.
     * @param string $method The HTTP method (verb).
     */
    public function match($url, $method)
    {
        $newurl = preg_replace_callback(
            '@\((%[0-9\.]{0,}[asdf]):(\w+)\)@',
            function ($matches) {
                switch (substr($matches[1], -1)) {
                    case 's': return "(?'{$matches[2]}'[^/]+?)";
                    case 'd': return "(?'{$matches[2]}'\d+?)";
                    case 'f': return "(?'{$matches[2]}'\d+\.\d+?)";
                    case 'a': return "(?'{$matches[2]}'.*?)";
                }
            },
            str_replace('.', '\.', trim($this->url))
        );
        if (preg_match("@^$newurl$@", $url, $matches)
            && in_array($method, $this->verbs)
        ) {
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
            "@\((%[0-9\.]{0,}[asdf]):(\w+)\)@",
            function ($match) use ($arguments) {
                return $arguments[$match[2]];
            },
            $this->url
        ));
    }
}

