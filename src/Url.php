<?php

namespace Reroute;

abstract class Url
{
    protected $url;
    protected $verbs;

    public abstract function match($url);
    public abstract function generate(array $arguments = []);

    public function __construct($url, array $verbs = ['GET'])
    {
        $this->url = $url;
        $this->verbs = $verbs;
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

    protected function full($url = null)
    {
        if (!isset($url)) {
            $url = $this->url;
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
        return $url;
    }

    /**
     * Temporarily redirect to the URL associated with state $name.
     *
     * @param string $name The state name to resolve.
     * @param array $arguments Additional arguments needed to build the URL.
     * @return void
     */
    public function redirect(array $arguments = [])
    {
        header("Location: ".$this->generate($arguments), true, 302);
        die();
    }
    
    /**
     * Permanently redirect to the URL associated with state $name.
     *
     * @param string $name The state name to resolve.
     * @param array $arguments Additional arguments needed to build the URL.
     * @return void
     */
    public function move(array $arguments = [])
    {
        header("Location: ".$this->generate($arguments), true, 301);
        die();
    }
}

