<?php

namespace Monolyth\Reroute;

use DomainException;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequestFactory;
use League\Pipeline\PipelineBuilder;
use League\Pipeline\Pipeline;
use League\Pipeline\StageInterface;

/**
 * The main Router class. Represents a (group of) routes which can be matched
 * either in a League\Pipeline or something compatible, or by directly invoking
 * the instance.
 */
class Router implements StageInterface
{
    /**
     * @var array
     * "Global" storing all named states, for reference.
     */
    protected static $namedStates = [];

    /**
     * @var array
     * Array storing defined routes.
     */
    protected $routes = [];

    /**
     * @var Monolyth\Reroute\State
     * Endstate for this route.
     */
    protected $state;

    /**
     * @var Psr\Http\Message\RequestInterface
     * Request object for the current request.
     */
    protected $request;

    /**
    * @var string
     * Host to use for every URL. Defaults to http://localhost
     * Note that this is fine if all URLs are on the same domain anyway, and
     * you're not passing the host name during resolve.
     */
    protected $host = 'http://localhost';

    /**
     * @var string
     * Name of the current endstate.
     */
    protected $name = null;

    /**
     * @var array
     * Hash of matched arguments for the resolved route.
     */
    protected static $matchedArguments = [];

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
        $this->request = ServerRequestFactory::fromGlobals();
        $this->url = $this->normalize($url);
        $this->pipeline = new PipelineBuilder;
        if (isset($pipe)) {
            $this->pipeline->add($pipe);
        }
        self::$matchedArguments = [];
    }

    /**
     * Adds a callable to the pipeline. The first argument is the payload (i.e.
     * request or response object). Subsequent arguments are taken from the
     * currently matched URL parameters.
     *
     * @param callable $stages Callable stages to add.
     * @return Monolyth\Reroute\Router
     * @throws InvalidArgumentException if any of the additional argument wasn't
     *  matched by name in the URL.
     */
    public function pipe(callable ...$stages) : Router
    {
        foreach ($stages as $stage) {
            if (!($stage instanceof StageInterface)) {
                $stage = new Pipe(function ($payload) use ($stage) {
                    if ($stage instanceof Closure) {
                        $reflection = new ReflectionFunction($stage);
                    } elseif (is_array($stage)) {
                        $reflection = new ReflectionMethod($stage[0], $stage[1]);
                    } else {
                        $reflection = new ReflectionMethod($stage, '__invoke');
                    }
                    $parameters = $reflection->getParameters();
                    $args = [];
                    foreach ($parameters as $key => $param) {
                        if (!$key) {
                            $args[] = $payload;
                        } elseif (isset(self::$matchedArguments[$param->name])) {
                            $args[] = self::$matchedArguments[$param->name];
                        } else {
                            throw new InvalidArgumentException(
                                "Pipe expects variable {$param->name}, but it is ".
                                "not present in the URL being resolved."
                            );
                        }
                    }
                    return call_user_func_array($stage, $args);
                });
            }
            $this->pipeline->add($stage);
        }
        return $this;
    }

    /**
     * Setup (part of) a URL for catching. The chain is called on match and
     * before control is delegated.
     *
     * @param string|null $url The URL(part) to match for this state. If null,
     *  something randomly invalid is used (useful for defining named states for
     *  error pages).
     * @param callable $callback Optional grouping callback.
     * @return Monolyth\Reroute\Router A new sub-router.
     */
    public function when($url, callable $callback = null) : Router
    {
        if (is_null($url)) {
            $url = '!!!!'.rand(0, 999).microtime();
        } else {
            $replace = function ($match) {
                $base = "(?'{$match[1]}'[a-zA-Z0-9-_]+";
                if (isset($match[2]) && $match[2] == '?') {
                    if (isset($match[3]) && $match[3] == '/') {
                        $base .= '/';
                    }
                    $base .= ')?';
                } else {
                    $base .= ')'.$match[3];
                }
                return $base;
            };
            // Brace style to regex:
            $url = preg_replace_callback(
                '@{([a-z]\w*)}(\??)(/?)@',
                $replace,
                $url
            );
            // Angular style to regex:
            $url = preg_replace_callback(
                '@:([a-z]\w*)(\??)(/?)@',
                $replace,
                $url
            );
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
            $this->routes[$url] = new Router($url);
        }
        if (isset($callback)) {
            $callback($this->routes[$url]);
        }
        return $this->routes[$url];
    }

    /**
     * If the current request URI ends with this URL, yield the associated
     * state. A state can be anything but will get wrapped in a `State` object.
     *
     * @param string $name The (preferably) unique optional name of this state.
     * @param mixed $state A valid state for the matched URL.
     * @return self The current router, for chaining.
     * @see Monolyth\Reroute\Route::generate
     */
    public function then($name, $state = null) : Router
    {
        if (!isset($state)) {
            $state = $name;
            $name = null;
        }
        $this->name = $name;
        if (!isset($this->state)) {
            $this->state = new State($name, $state);
            $this->pipe(new Pipe(function ($request) {
                return $request instanceof ResponseInterface ?
                    $request :
                    $this->state;
            }));
        } else {
            $this->state->addCallback('GET', $state);
        }
        if (isset($name)) {
            self::$namedStates[$name] = $this;
        }
        return $this;
    }

    /**
     * Use the defined $state if the HTTP action specifically matches `"POST"`.
     *
     * @param mixed $state A valid state to respond with.
     * @return self The current router, for chaining.
     */
    public function post($state) : Router
    {
        if (!isset($this->state)) {
            $this->then(null, function() {});
        }
        $this->state->addCallback('POST', $state);
        return $this;
    }

    /**
     * Use the defined $state if the HTTP action specifically matches `"PUT"`.
     *
     * @param mixed $state A valid state to respond with.
     * @return self The current router, for chaining.
     */
    public function put($state) : Router
    {
        if (!isset($this->state)) {
            $this->then(null, function() {});
        }
        $this->state->addCallback('PUT', $state);
        return $this;
    }

    /**
     * Use the defined $state if the HTTP action specifically matches
     * `"DELETE"`.
     *
     * @param mixed $state A valid state to respond with.
     * @return self The current router, for chaining.
     */
    public function delete($state) : Router
    {
        if (!isset($this->state)) {
            $this->then(null, function() {});
        }
        $this->state->addCallback('DELETE', $state);
        return $this;
    }

    /**
     * Use the defined $state if the HTTP action specifically matches `"HEAD"`.
     *
     * @param mixed $state A valid state to respond with.
     * @return self The current router, for chaining.
     */
    public function head($state) : Router
    {
        if (!isset($this->state)) {
            $this->then(null, function() {});
        }
        $this->state->addCallback('HEAD', $state);
        return $this;
    }

    /**
     * Use the defined $state if the HTTP action specifically matches
     * `"OPTIONS"`.
     *
     * @param mixed $state A valid state to respond with.
     * @return self The current router, for chaining.
     */
    public function options($state) : Router
    {
        if (!isset($this->state)) {
            $this->then(null, function() {});
        }
        $this->state->addCallback('OPTIONS', $state);
        return $this;
    }

    /**
     * A front to `__invoke` for compatibility with older League\Pipeline
     * versions.
     *
     * @param Psr\Http\Message\RequestInterface $request The request to handle.
     *  Defaults to the current request.
     * @return Monolyth\Reroute\State|null If succesful, the corresponding state is
     *  returned, otherwise null (the implementor should then show a 404 or
     *  something else notifying the user).
     * @see Monolyth\Reroute\Router::__invoke
     */
    public function process($payload) :? State
    {
        return $this($payload);
    }

    /**
     * Attempt to resolve a Monolyth\Reroute\State associated with a request.
     *
     * @param Psr\Http\Message\RequestInterface $request The request to handle.
     *  Defaults to the current request.
     * @return mixed If succesful, the corresponding state is invoked and its
     *  response returned, otherwise null (the implementor should then show a
     *  404 or something else notifying the user).
     */
    public function __invoke(RequestInterface $request = null)
    {
        if (isset($request)) {
            $this->request = $request;
        }
        $url = $this->request->getUri().'';
        $url = $this->normalize($url);
        $parts = parse_url($url);
        unset($parts['query'], $parts['fragment']);
        $parts += parse_url($this->host);
        $url = http_build_url('', $parts);
        $test = preg_match("@^{$this->url}$@", $url, $matches);
        unset($matches[0]);
        self::$matchedArguments += $matches;
        $response = $this->pipeline->build()->process($request);
        if ($test) {
            if ($response instanceof State) {
                return $response(self::$matchedArguments, $request);
            }
        }
        if ($response instanceof ResponseInterface) {
            return $response;
        }
        foreach ($this->routes as $match => $router) {
            if (preg_match("@^$match@", $url, $matches)) {
                unset($matches[0]);
                self::$matchedArguments = $matches + self::$matchedArguments;
                if ($res = $router($request)) {
                    self::$matchedArguments = [];
                    return $res;
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
        if (!isset(self::$namedStates[$name])) {
            throw new DomainException("Unknown state: $name");
        }
        return self::$namedStates[$name]->state;
    }

    /**
     * Generate a URI for a named state, using optional $arguments.
     *
     * @param string $name The name of the state for which we're building a URI.
     * @param array $arguments Optional hash of arguments to inject into the
     *  URI. The keys can be either the argument names, or you can pass them in
     *  the correct order with numeric indices.
     * @param bool $shortest If true (the default), the generated URI won't
     *  include scheme/hostname if they are the same as for the current request.
     * @return string A URI pointing to the requested state.
     * @throws DomainException if no state called `$name` exists.
     */
    public function generate($name, array $arguments = [], $shortest = true)
    {
        if (!isset(self::$namedStates[$name])) {
            throw new DomainException("Unknown state: $name");
        }
        $state = self::$namedStates[$name];
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
                && (isset($arguments[$named[1]]) || isset(self::$matchedArguments[$named[1]]))
            ) {
                $url = str_replace(
                    $var,
                    $arguments[$named[1]] ?? self::$matchedArguments[$named[1]],
                    $url
                );
                unset($arguments[$named[1]]);
            } elseif ($arguments) {
                $url = str_replace($var, array_shift($arguments), $url);
            } else {
                $url = str_replace($var, '', $url);
            }
        }
        if ($shortest and $current = $this->currentHost()) {
            $url = preg_replace("@^$current/?@", '/', $url);
        }
        return preg_replace('@(?<!:)/{2,}@', '/', $url);
    }

    /**
     * Internal helper method to "normalize" the current URI (e.g. make sure
     * it has a scheme and a host).
     *
     * @param string $url The URL to normalize.
     * @param string $scheme Optional fallback scheme. Defaults to `'http'`.
     * @param string $host Optional fallback host. Defaults to `'localhost'`.
     * @return string A fully formed URI.
     */
    protected function normalize($url, $scheme = 'http', $host = 'localhost') : string
    {
        if (preg_match("@^(https?)@", $url, $match)) {
            $scheme = $match[1];
        }
        if (preg_match("@^$scheme://(.*?)(/|$)@", $url, $match)) {
            $host = $match[1];
        }
        $url = preg_replace("@^$scheme://.*?(/|$)@", '$1', $url);
        return "$scheme://$host$url";
    }

    /**
     * Returns the current host (e.g. `'http://localhost/'`).
     *
     * @return string The current host.
     */
    public function currentHost() : string
    {
        $url = $this->request->getUri();
        $parts = parse_url($url);
        unset($parts['query'], $parts['fragment'], $parts['path']);
        return http_build_url($parts);
    }

    /**
     * Return the full current URI, without query and fragment parts.
     *
     * @return string The current URI.
     */
    public function currentUrl() : string
    {
        $url = $this->request->getUri();
        $parts = parse_url($url);
        unset($parts['query'], $parts['fragment']);
        return http_build_url($parts);
    }
}

