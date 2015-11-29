<?php

namespace Reroute;

use DomainException;
use ReflectionFunction;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Request;
use League\Pipeline\PipelineBuilder;
use League\Pipeline\Pipeline;

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
     * @param League\Pipeline\Pipeline $pipe Optional pipeline to chain onto.
     * @return void
     */
    public function __construct($url = null, Pipeline $pipe = null)
    {
        $this->url = $this->normalize($url);
        $this->pipeline = new PipelineBuilder;
        if (isset($pipe)) {
            $this->pipeline->add($pipe);
        }
    }

    public function pipe(callable $state)
    {
        $this->pipeline->add($state);
        return $this;
    }

    /**
     * Setup (part of) a URL for catching. The chain is called on match and
     * before control is delegated.
     *
     * to (sub) routers or `then`
     * calls.
     *
     * @param string|null $url The URL(part) to match for this state. If null,
     *  something randomly invalid is used (useful for defining named states for
     *  error pages).
     * @param callable $callback Optional grouping callback.
     * @return Reroute\Router A new sub-router.
     */
    public function when($url, callable $callback = null)
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
        if (!isset($this->routes[$url])) {
            $this->routes[$url] = new Router($url, $this->pipeline->build());
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
        $this->state = new State($name, $state);
    }

    /**
     * Attempt to resolve a Reroute\State associated with a request.
     *
     * @param mixed $payload Payload for the pipeline. For convenience, if
     *  $payload is a Zend\Diactoros\ServerRequest, the payload is transformed
     *  into a hash for subsequent calls.
     * @return Reroute\State|null If succesful, the corresponding state is
     *  returned, otherwise null (the implementor should then show a 404 or
     *  something else notifying the user).
     */
    public function __invoke($payload)
    {
        if (is_object($payload) && $payload instanceof ServerRequest) {
            $payload = ['request' => $payload];
        }
        extract($payload);
        if (!isset($url)) {
            $url = $request->getUri().'';
        }
        $url = $this->normalize($url);
        $parts = parse_url($url);
        unset($parts['query'], $parts['fragment']);
        $parts += parse_url($this->host);
        $url = http_build_url('', $parts);
        foreach ($this->routes as $match => $router) {
            if (preg_match("@^$match(.*)$@", $url, $matches)) {
                $last = array_pop($matches);
                unset($matches[0]);
                $payload += compact('url', 'matches');
                if (!strlen($last)) {
                    $this->pipe($router->state);
                    return $this->pipeline
                        ->build()
                        ->process($payload);
                } else {
                    return $router($payload);
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

