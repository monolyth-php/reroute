<?php

namespace reroute;

class Router
{
    /**
     * Array storing defined routes.
     */
    protected $routes = [];
    /**
     * String to prefix to every URL. Defaults to the current domain.
     */
    protected $prefix;

    /**
     * Define a state. Any existing state with the same name will be overridden
     * (see Router::get below on how to extend existing states).
     *
     * @param string $name The unique name identifying this state.
     * @param string $verb The HTTP verb associated with the state.
     * @param string $url The URL to match. Can be a FQDN or something relative
     *                    (see Router::under below).
     * @param callable $callback The callback specifying what this state should
     *                           actually do.
     * @return void
     */
    public function state($name, $verb, $url, callable $callback)
    {
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
    }

    /**
     * Get the state object identified by $name. This can be used for further
     * processing before control is relinquished.
     */
    public function get($name)
    {
    }
}

