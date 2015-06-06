<?php

namespace Reroute;
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
    public function state($name, Url $url, callable $callback)
    {
        $url->prefix($this->prefix);
        $state = new State($this->host, $url, $callback);
        if (isset($this->group)) {
            $state->group($this->group);
        }
        $this->routes[] = $state;
        $this->states[$name] = $state;
    }

    /**
     * For all routes defined inside $callback, use $host.
     *
     * @param string $host The host to use.
     * @param callable $callback Callback defining routes for this host. It
     *                           gets passed a single argument (the router
     *                           instance).
     * @return void
     */
    public function host($host, callable $callback)
    {
        $previous = $this->host;
        $this->host = $host;
        $callback($this);
        $this->host = $previous;
    }

    /**
     * For all routes defined inside $callback, prepend $prefix first.
     *
     * @param string $prefix The prefix to be prepended to the current path.
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
        if (isset($previous)) {
            if (!is_array($previous)) {
                $this->group = [$this->group];
            }
            $this->group[] = $group;
        } else {
            $this->group = $group;
        }
        $callback($this);
        $this->group = $previous;
    }

    /**
     * Attempt to resolve a reroute\State associated with $url.
     *
     * @param string $url The url to resolve.
     * @param string $method The HTTP method (GET, POST etc.) to match on.
     * @return reroute\State If succesful, the corresponding state is returned.
     * @throws reroute\ResolveException When $url matches no known state
     *                                  (handling of the exception is up to the
     *                                  implementation, probably a 404).
     */
    public function resolve($url, $method = 'GET')
    {
        $parts = parse_url($url);
        $defaults = [
            'scheme' => 'http',
            'host' => 'localhost',
        ];
        unset($parts['query'], $parts['fragment']);
        foreach ($defaults as $key => $value) {
            if (!isset($parts[$key])) {
                $parts[$key] = $value;
            }
        }
        $url = http_build_url('', $parts);

        foreach ($this->routes as $state) {
            if ($state->match($url, $method)) {
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
}

