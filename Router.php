<?php

namespace reroute;
use DomainException;

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
    /**
     * String to prefix to every URL. Defaults to the current domain.
     */
    protected $prefix = '';
    /**
     * Group the state should be in (faux-namespace).
     */
    protected $group;

    /**
     * Define a state. Any existing state with the same name will be overridden
     * (see Router::get below on how to extend existing states).
     *
     * @param string $name The unique name identifying this state.
     * @param string $url The URL to match. Can be a FQDN or something relative
     *                    (see Router::under below).
     * @param callable $callback The callback specifying what this state should
     *                           actually do.
     * @return void
     */
    public function state($name, $url, callable $callback)
    {
        $url = $this->prefix.$url;
        $url = $this->fullUrl($url);
        $state = new State($url, $callback);
        if (isset($this->group)) {
            $state->group($this->group);
        }
        $url = '@^'.str_replace('@', '\@', $this->fullUrl($url)).'$@';
        $this->routes[$url] = $state;
        $this->states[$name] = $state;
    }

    /**
     * For all routes defined inside $callback, prepend $prefix first.
     *
     * @param string $prefix The prefix to be prepended. May include host
     *                       and protocol.
     * @param callable $callback Callback defining routes with this prefix. It
     *                           gets passed a single argument (the router
     *                           instance).
     * @return void
     */
    public function under($prefix, callable $callback)
    {
        $previous = $this->prefix;
        $this->prefix .= $prefix;
        $callback($this);
        $this->prefix = $previous;
    }

    /**
     * Add all states defined inside $callback to $group.
     *
     * @param string $group The group to add these states to.
     * @param callable $callback Callback defining states in this group. It
     *                           gets passed a single argument (the router
     *                           instance).
     * @return void
     */
    public function group($group, callable $callback)
    {
        $previous = isset($this->group) ? $this->group : null;
        $this->group = $group;
        $callback($this);
        $this->group = $previous;
    }

    /**
     * Attempt to resolve a reroute\State associated with $url.
     *
     * @param string $url The url to resolve.
     * @return reroute\State If succesful, the corresponding state is returned.
     * @throws reroute\ResolveException When $url matches no known state
     *                                  (handling of the exception is up to the
     *                                  implementation, probably a 404).
     */
    public function resolve($url)
    {
        // Remove any $_GET values; they're not needed for matching.
        $url = preg_replace('@\?.*?$@', '', $url);
        $url = $this->fullUrl($url);
        foreach ($this->routes as $route => $state) {
            if (preg_match($route, $url, $matches)) {
                unset($matches[0]);
                $state->arguments($matches);
                return $state;
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
            throw new DomainException("Unknown state: $name");
        }
        return $this->states[$name];
    }

    /**
     * Return the URL associated with the state $name.
     *
     * @param string $name The state name to resolve.
     * @param array $arguments Additional arguments needed to build the URL.
     * @return string The generated URL, with optional scheme/domain prefixed.
     */
    public function absolute($name, $arguments = [])
    {
        $args = func_get_args();
        array_shift($args);
        foreach ($this->routes as $url => $state) {
            if ($state === $this->states[$name]) {
                // Remove regex parts:
                $url = substr($url, 2, -2);
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
                return $url;
            }
        }
        return null;
    }

    /**
     * Return the URL associated with the state $name, with domain part stripped
     * if we can detect we already are on that domain (most common use case).
     *
     * @param string $name The state name to resolve.
     * @param array $arguments Additional arguments needed to build the URL.
     * @return string The generated URL, with scheme/domain optionally stripped.
     *
     * @todo Abstract away key names in $_SERVER, they're platform-specific...
     */
    public function url($name, array $arguments = [])
    {
        if ($url = $this->absolute($name, $arguments)) {
            if (isset($_SERVER['SERVER_NAME'])) {
                $url = str_replace(
                    "http://{$_SERVER['SERVER_NAME']}",
                    '',
                    $url
                );
            }
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
    public function redirect($name, array $arguments = [])
    {
        header("Location: ".$this->absolute($name, $arguments), true, 302);
        die();
    }

    /**
     * Permanently redirect to the URL associated with state $name.
     *
     * @param string $name The state name to resolve.
     * @param array $arguments Additional arguments needed to build the URL.
     * @return void
     */
    public function move($name, array $arguments = [])
    {
        header("Location: ".$this->absolute($name, $arguments), true, 301);
        die();
    }

    /**
     * Internal helper method to generate a full URL as needed for matching.
     *
     * @param string $url The supplied URL we want to match.
     * @return string A fully regexable URL.
     */
    protected function fullUrl($url)
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

