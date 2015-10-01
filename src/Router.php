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
     * Endstate for this route.
     */
    protected $state;

    /**
     * Host to use for every URL. Defaults to http://localhost
     *
     * Note that this is fine if all URLs are on the same domain anyway, and
     * you're not passing the host name during resolve.
     */
    protected $host = 'http://localhost';

    /**
     * Name of the current endstate.
     */
    protected $name = null;

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
            $url = '!!!!'.rand(0, 999).microtime();
        } else {
            // Brace style to regex:
            $url = preg_replace('@{([a-z]\w*)}@', "(?'\\1'\w+)", $url);
            // Angular style to regex:
            $url = preg_replace('@:([a-z]\w*)@', "(?'\\1'\w+)", $url);
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
        if (count($args) == 2 && $intermediate) {
            // If the second argument is a callable that groups, use that as a
            // callback and empty the intermediate:
            $reflect = new ReflectionFunction($intermediate);
            $args = $reflect->getParameters();
            if (count($args) == 1 && $args[0]->name == 'router') {
                $callback = $intermediate;
                $intermediate = null;
            }
        } elseif (count($args) == 3 && is_callable($args[2])) {
            $callback = $args[2];
        }
        if (!isset($intermediate) && isset($this->intermediate)) {
            $intermediate = $this->intermediate;
        }
        if (!isset($this->routes[$url])) {
            $this->routes[$url] = new Router($url, $intermediate);
        }
        if (isset($callback)) {
            $callback($this->routes[$url]);
        }
        return $this->routes[$url];
    }

    /**
     * If the current REQUEST_URI ends with this URL, yield the associated
     * state. A state can be anything but will get wrapped in a `State` object.
     *
     * @param string $name The (preferably) unique name of this state.
     * @param mixed $state A valid state for the matched URL.
     * @return Reroute\State A State object.
     * @see Reroute\Route::generate
     */
    public function then($name, $state)
    {
        $this->name = $name;
        $this->state = function (array $matches) use ($name, $state) {
            $parser = new ArgumentsParser($this->intermediate);
            $args = $parser->parse($matches);
            if (false !== call_user_func_array($this->intermediate, $args)) {
                return new State($name, $state, $matches);
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
        foreach ($this->routes as $match => $router) {
            if (preg_match("@^$match(.*)$@", $url, $matches)) {
                $last = array_pop($matches);
                unset($matches[0]);
                if (!strlen($last)) {
                    return call_user_func($router->state, $matches);
                } elseif ($found = $router->resolve($url, $method)) {
                    return $found;
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
        if (!($state = $this->findStateRecursive($name))) {
            throw new DomainException("Unknown state: $name");
        }
        return call_user_func($state->state, []);
    }

    public function generate($name, array $arguments = [], $shortest = true)
    {
        if (!($state = $this->findStateRecursive($name))) {
            throw new DomainException("Unknown state: $name");
        }
        $url = $state->url;
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
            $current = $this->currentHost();
            $url = preg_replace("@^$current@", '', $url);
        }
        return $url;
    }

    public function redirect($name, array $arguments = [], $force = false)
    {
        $url = $this->generate($name, $arguments, false);
        if ($url != $this->currentUrl() || $force) {
            header("Location: $url", true, 302);
            die();
        }
    }

    public function move($name, array $arguments = [], $force = false)
    {
        $url = $this->generate($name, $arguments, false);
        if ($url != $this->currentUrl() || $force) {
            header("Location: $url", true, 301);
            die();
        }
    }

    protected function findStateRecursive($name)
    {
        if (!isset($this->state) || $this->name != $name) {
            foreach ($this->routes as $url => $router) {
                if ($state = $router->findStateRecursive($name)) {
                    return $state;
                }
            }
            return null;
        }
        return $this;
    }

    protected function normalize($url, $scheme = 'http', $host = 'localhost')
    {
        $parts = parse_url($url);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : $scheme;
        $host = isset($parts['host']) ? $parts['host'] : $host;
        $url = preg_replace("@^$scheme://$host@", '', $url);
        return "$scheme://$host$url";
    }

    public function currentHost()
    {
        $protocol = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $protocol = 'https';
        }
        return "$protocol://{$_SERVER['HTTP_HOST']}";
    }

    public function currentUrl()
    {
        $url = $this->currentHost().$_SERVER['REQUEST_URI'];
        $parts = parse_url($url);
        unset($parts['query'], $parts['fragment']);
        return http_build_url($parts);
    }
}

