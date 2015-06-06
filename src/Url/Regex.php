<?php

namespace Reroute\Url;

use Reroute\Url;

class Regex extends Url
{
    public function match($url, $method)
    {
        if (preg_match("@^{$this->host}{$this->url}$@", $url, $matches)
            && in_array($method, $this->verbs)
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
        preg_match_all(
            "@\((.*?)\)@",
            $url,
            $variables,
            PREG_SET_ORDER
        );
        foreach ($variables as $idx => $var) {
            if (preg_match("@\?'(\w+)'@", $var[1], $named)
                && isset($arguments[$named[1]])
            ) {
                $url = str_replace(
                    $var[0],
                    $arguments[$named[1]],
                    $url
                );
            } elseif (isset($arguments[$idx])) {
                $url = str_replace($var[0], $argugments[$idx], $url);
            } else {
                $url = str_replace($var[0], '', $url);
            }
        }
        return $this->full($url);
    }
}

