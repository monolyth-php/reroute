<?php

namespace Reroute\Url;

use Reroute\Url;

class Regex extends Url
{
    public function match($url)
    {
        if (preg_match("@^{$this->url}$@", $url, $matches)) {
            unset($matches[0]);
            return $matches;
        }
        return null;
    }

    public function generateAbsolute(array $arguments = [])
    {
        $url = $this->url;
        // Remove HTTP verb(s):
        $url = preg_replace('@:[^:]+?$@', '', $url);
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

