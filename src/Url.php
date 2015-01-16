<?php

namespace Reroute;

abstract class Url
{
    protected $url;

    public abstract function match($url);

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function prefix($prefix)
    {
        $url = $this->url;
        $parts = parse_url($url);
        $this->url = str_replace(
            $parts['path'],
            $prefix.$parts['path'],
            $url
        );
    }

    protected function full($url)
    {
        $verb = ':GET';
        if (preg_match("@:([()|A-Z]+)$@", $url)) {
            $verb = '';
        }
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) {
            $url = sprintf(
                'http://%s%s',
                isset($_SERVER['SERVER_NAME']) ?
                    $_SERVER['SERVER_NAME'] :
                    'localhost',
                $url
            );
        }
        return $url.$verb;
    } 
}

