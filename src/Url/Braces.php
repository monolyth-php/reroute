<?php

namespace Reroute\Url;

use Reroute\Url;

class Braces extends Url
{
    /**
     * Check if this URL is a match.
     *
     * @param string $url The requested URL.
     * @param string $method The HTTP method (verb).
     */
    public function match($url, $method)
    {
        $try = $this->url;
        $oldmatches = [];
        if ($try instanceof Url) {
            $oldmatches = $try->match($url, $method);
        }
        $try = preg_replace('@{(\w+)}@', '(\w+)', $try);
        if (preg_match("@^$try$@", $url, $matches)) {
            unset($matches[0]);
            $matches = array_merge($oldmatches, $matches);
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
            "@{(\w+)}@",
            function ($match) use ($arguments) {
                return $arguments[$match[1]];
            },
            $this->url instanceof Url ?
                $this->url->generate($arguments) :
                $this->url
        ));
    }
}

