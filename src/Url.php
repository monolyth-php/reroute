<?php

namespace Reroute;

abstract class Url
{
    protected $host;
    protected $url;
    protected $verbs;

    public abstract function match($url, $method);
    public abstract function generate(array $arguments = []);

    public function __construct($url = '/', array $verbs = ['GET'])
    {
        $this->url = $url;
        $this->verbs = $verbs;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function prefix($prefix)
    {
        $url = $this->url;
        $url = str_replace('?', '__QUESTION_MARK__', $url);
        $path = parse_url($url, PHP_URL_PATH);
        $this->url = str_replace(
            [$path, '__QUESTION_MARK__'],
            [$prefix.$path, '?'],
            $url
        );
    }

    protected function full($url = null)
    {
        if (!isset($url)) {
            $url = $this->url;
        }
        $fallback = parse_url($this->host);
        $parts = $fallback + parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) {
            $parts['scheme'] = 'http';
            $parts['host'] = isset($_SERVER['HTTP_HOST']) ?
                $_SERVER['HTTP_HOST'] :
                'localhost';
        }
        return http_build_url($parts);
    }

    /**
     * Generate the current URL, but keep it as short as possible (i.e., any
     * parts already in the current location can be omitted).
     *
     * @param string $current The current, full URL to test against.
     * @param array $arguments Additional arguments needed to build the URL.
     */
    public function short($current, array $arguments = [])
    {
        $url = $this->generate($arguments);
        $parts = parse_url($current);
        $current = sprintf('%s://%s/', $parts['scheme'], $parts['host']);
        if (stripos($url, $current) === 0) {
            $url = preg_replace("@^$current@i", '/', $url);
        }
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
        if (!in_array('GET', $this->verbs)) {
            throw new IllegalRedirectException;
        }
        header("Location: ".$this->generate($arguments), true, 302);
    }
    
    /**
     * Permanently redirect to the URL associated with state $name.
     *
     * @param array $arguments Additional arguments needed to build the URL.
     * @return void
     */
    public function move(array $arguments = [])
    {
        if (!in_array('GET', $this->verbs)) {
            throw new IllegalRedirectException;
        }
        header("Location: ".$this->generate($arguments), true, 301);
    }
}

