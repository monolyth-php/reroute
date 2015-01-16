<?php

namespace Reroute;

abstract class Url
{
    protected $url;
    protected $verbs;

    public abstract function match($url, $method);
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
     * Generate the current URL, but keep it as short as possible (i.e., any
     * parts already in the current location can be omitted).
     *
     * @param array $arguments Additional arguments needed to build the URL.
     */
    public function short(array $arguments = [])
    {
        $url = $this->generate($arguments);
        return $url;
    }

    /**
     * Temporarily redirect to the URL associated with state $name.
     *
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
     * @param array $arguments Additional arguments needed to build the URL.
     * @return void
     */
    public function move(array $arguments = [])
    {
        header("Location: ".$this->generate($arguments), true, 301);
        die();
    }
}

