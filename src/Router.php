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
    public function state($name, Url $url, callable $callback)
    {
        $url->prefix($this->prefix);
        $state = new State($url, $callback);
        if (isset($this->group)) {
            $state->group($this->group);
        }
        $this->routes[] = $state;
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
     * @param string $method The HTTP method (GET, POST etc.) to match on.
     * @return reroute\State If succesful, the corresponding state is returned.
     * @throws reroute\ResolveException When $url matches no known state
     *                                  (handling of the exception is up to the
     *                                  implementation, probably a 404).
     */
    public function resolve($url, $method = 'GET')
    {
        /**
         * Remove any $_GET values; they're not needed for matching.
         */
        $url = preg_replace('@\?.*?$@', '', $url);

        foreach ($this->routes as $route => $state) {
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
}

