<?php

namespace Reroute;

use DomainException;
use ReflectionFunction;

class Router
{
    /**
     * Array storing defined routes.
     */
    protected $routes = [];

    /**
     * Array storing defined states.
     */
    protected $states = [];

    protected $final;

    /**
     * Host to use for every URL. Defaults to http://localhost
     *
     * Note that this is fine if all URLs are on the same domain anyway, and
     * you're not passing the host name during resolve.
     */
    protected $host = 'http://localhost';

    /**
     * String to prefix to every URL.
     */

    protected $prefix = '';

    /**
     * Group the state should be in (faux-namespace).
     */
    protected $group;

    /**
     * Constructor. In most cases you won't need to worry about the constructor
     * arguments, but optionally you can pass a path part all routes _must_
     * match (e.g. if Reroute only needs to catch parts of your project).
     *
     * @param string $url The path part _all_ URLs for this router must fall
     *  under in order to match.
     * @param callable $intermediate Optional intermediate callback.
     * @return void
     */
    public function __construct($url = null, callable $intermediate = null)
    {
        $this->url = $this->normalize($url);
        $this->intermediate = isset($intermediate) ?
            $intermediate :
            function () { return $this; };
    }

    /**
     * Setup (part of) a URL for catching. The intermediate callback is called
     * on match and before control is delegated to (sub) routers or `then`
     * calls.
     *
     * @param string|null $url The URL(part) to match for this state. If null,
     *  something randomly invalid is used (useful for defining named states for
     *  error pages).
     * @param callable $intermediate Optional intermediate callback.
     * @param callable $callback Optional grouping callback.
     * @return Reroute\Router A new sub-router.
     */
    public function when($url, callable $intermediate = null)
    {
        if (is_null($url)) {
            $url = '!@#$%^&*()'.rand(0, 999).microtime();
        } else {
            // Brace style to regex:
            $url = preg_replace('@{(\w+)}@', "(?'\\1'\w+)", $url);
            // Angular style to regex:
            $url = preg_replace('@:(\w+)@', "(?'\\1'\w+)", $url);
        }
        $parts = parse_url($this->url);
        $check = parse_url($url);
        if (!isset($check['host'])) {
            $url = $this->url.$url;
        }
        $url = $this->normalize(
            $url,
            isset($parts['scheme']) ? $parts['scheme'] : 'http',
            isset($parts['host']) ? $parts['host'] : 'localhost'
        );
        $url = preg_replace("@(?<!:)/{2,}@", '/', $url);
        $args = func_get_args();
        if (count($args == 2) && $intermediate) {
            // If the second argument is a callable that groups, use an empty
            // lamba as an intermediate:
            $reflect = new ReflectionFunction($intermediate);
            $args = $reflect->getParameters();
            if (count($args) == 1 && $args[0]->name == 'router') {
                $callback = $intermediate;
                $intermediate = null;
            }
        } elseif (count($args) == 3 && is_callable($args[2])) {
            $callback = $args[2];
        }
        $this->routes[$url] = new Router($url, $intermediate);
        if (isset($callback)) {
            $callback($this->routes[$url]);
        }
        return $this->routes[$url];
    }

    /**
     * Define a named state. Any existing state with the same name will be
     * overridden (see Router::get below on how to extend existing states).
     *
     * @param string $name The unique name identifying this state.
     * @param string $url The URL to match. Can be a FQDN or something relative.
     * @param callable $intermediate Optional intermediate callback.
     * @param callable $callback Optional grouping callback.
     * @return Reroute\Router A new sub-router.
     * @return void
     */
    public function state($name, $url, callable $intermediate = null)
    {
        $args = func_get_args();
        array_shift($args);
        $state = call_user_func_array([$this, 'when'], $args);
        $this->states[$name] = $state;
        return $state;
    }

    /**
     * If the current REQUEST_URI ends with this URL, yield the associated
     * state. A state can be anything but will get wrapped in a `State` object.
     *
     * @param mixed $state A valid state for the matched URL.
     * @return Reroute\State A State object.
     */
    public function then($state)
    {
        $this->final = function (array $matches) use ($state) {
            $parser = new ArgumentsParser($this->intermediate);
            $args = $parser->parse($matches);
            if (false !== call_user_func_array($this->intermediate, $args)) {
                return new State($state, $matches);
            }
        };
    }

    /**
     * Attempt to resolve a Reroute\State associated with $url.
     *
     * @param string $url The url to resolve.
     * @param string $method The HTTP method (GET, POST etc.) to match on.
     * @return Reroute\State|null If succesful, the corresponding state is
     *  returned, otherwise null (the implementor should then show a 404 or
     *  something else notifying the user).
     */
    public function resolve($url, $method = 'GET')
    {
        $url = $this->normalize($url);
        $parts = parse_url($url);
        unset($parts['query'], $parts['fragment']);
        $parts += parse_url($this->host);
        $url = http_build_url('', $parts);
        foreach ($this->routes as $match => $state) {
            if (preg_match("@^$match(.*)$@", $url, $matches)) {
                $last = array_pop($matches);
                unset($matches[0]);
                if (!strlen($last)) {
                    return call_user_func($state->final, $matches);
                } else {
                    return $state->resolve($url, $method);
                }
            }
        }
        return null;
    }

    /**
     * Get the state object identified by $name. This can be used for further
     * processing before control is relinquished.
     */
    public function get($name)
    {
        if (!isset($this->states[$name])) {
            throw new DomainException("Unknown named state: $name");
        }
        if (!isset($this->states[$name]->final)) {
            throw new DomainException("State $name is not a final state.");
        }
        return call_user_func($this->states[$name]->final, []);
    }

    public function generate($name, array $arguments = [], $shortest = true)
    {
        if (!isset($this->states[$name])) {
            throw new DomainException("Unknown named state: $name");
        }
        if (!isset($this->states[$name]->final)) {
            throw new DomainException("State $name is not a final state.");
        }
        $url = $this->states[$name]->url;
        // For all arguments, map the values back into the URL:
        preg_match_all(
            "@\((.*?)\)@",
            $url,
            $variables,
            PREG_SET_ORDER
        );
        foreach ($variables as $idx => $var) {
            $var = $var[0];
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
        if ($shortest && isset($_SERVER['HTTP_HOST'])) {
            $protocol = 'http';
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
                $protocol = 'https';
            }
            $current = "$protocol://{$_SERVER['HTTP_HOST']}";
            $url = preg_replace("@^$current@", '', $url);
        }
        return $url;
    }

    public function redirect($name, array $arguments = [])
    {
        $url = $this->generate($name, $arguments, false);
        header("Location: $url", true, 302);
    }

    public function move($name, array $arguments = [])
    {
        $url = $this->generate($name, $arguments, false);
        header("Location: $url", true, 301);
    }

    protected function normalize($url, $scheme = 'http', $host = 'localhost')
    {
        $parts = parse_url($url);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : $scheme;
        $host = isset($parts['host']) ? $parts['host'] : $host;
        $url = preg_replace("@^$scheme://$host@", '', $url);
        return "$scheme://$host$url";
    }
}

