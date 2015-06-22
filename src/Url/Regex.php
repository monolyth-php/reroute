<?php

namespace Reroute\Url;

use Reroute\Url;

class Regex extends Url
{
    public function match($url, $method)
    {
        if (in_array($method, $this->verbs)
            && preg_match("@^{$this->host}{$this->url}$@", $url, $matches)
        ) {
            unset($matches[0]);
            $found = [];
            foreach ($matches as $key => $match) {
                if (is_numeric($key)) {
                    $found[] = $match;
                } else {
                    $found[$key] = $match;
                }
            }
            return $found;
        }
        return null;
    }

    public function generate(array $arguments = [])
    {
        $url = $this->url;
        // For all arguments, map the values back into the URL:
        preg_match(
            "@\((.*?)\)@",
            $url,
            $variables
        );
        foreach ($variables as $idx => $var) {
            if (preg_match("@\?'(\w+)'@", $var, $named)
                && isset($arguments[$named[1]])
            ) {
                $url = str_replace(
                    $var,
                    $arguments[$named[1]],
                    $url
                );
                unset($arguments[$named[1]]);
            } elseif ($arguments) {
                $url = str_replace($var, array_shift($arguments), $url);
            } else {
                $url = str_replace($var, '', $url);
            }
        }
        return $this->full($url);
    }
}

