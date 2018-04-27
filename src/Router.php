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
     * @return void
     */
    public function __construct(string $url)
    {
        $this->request = ServerRequestFactory::fromGlobals();
        $this->url = $this->normalize($url);
    }

    /**
     * Setup (part of) a URL for catching. The chain is called on match and
     * before control is delegated.
     *
     * @param string $url The URL(part) to match for this state.
     * @param string|null $name Optional name for this URL/state. Names are
     *  useful since they allow you to change the URL without having to change
     *  the generation anywhere (they'll "just work").
     * @param callable|null $callback Optional grouping callback. It gets called
     *  with a new subrouter with the `$url` as its base.
     * @return Monolyth\Reroute\State A new state representing the endpoint.
     */
    public function when(string $url, string $name = null, callable $callback = null) : State
    {
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
        if (isset($callback)) {
            if (!isset($this->routes[$url])) {
                $this->routes[$url] = new Router($url);
            }
            $callback($this->routes[$url]);
            if (!($state = $this->routes[$url]->getRootState())) {
                $state = $this->routes[$url]->when('/', $name);
            }
        } else {
            $state = $this->routes[$url] = new State($url, $name);
        }
        if (isset($name)) {
            self::$namedStates[$name] = $state;
        }
        return $state;
    }

    public function getRootState() :? State
    {
        return (isset($this->routes[$this->url]) && $this->routes[$this->url] instanceof State)
            ? $this->routes[$this->url]
            : null;
    }

    /**
     * A front to `__invoke` for compatibility with older League\Pipeline
     * versions.
     *
     * @param Psr\Http\Message\RequestInterface $request The request to handle.
     *  Defaults to the current request.
     * @return Psr\Http\Message\ResponseInterface|null
     * @see Monolyth\Reroute\Router::__invoke
     */
    public function process($request = null) :? ResponseInterface
    {
        return $this($request);
    }

    /**
     * Attempt to resolve a Monolyth\Reroute\State associated with a request.
     *
     * @param Psr\Http\Message\RequestInterface $request The request to handle.
     *  Defaults to the current request.
     * @param array $pipeline Optional array of pipes. Mostly for internal use.
     * @return Psr\Http\Message\ResponseInterface|null If succesful, the
     *  corresponding state is invoked and its response returned, otherwise null
     *  (the implementor should then show a 404 or something else notifying the
     *  user).
     */
    public function __invoke(RequestInterface $request = null, array $pipeline = []) :? ResponseInterface
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
        foreach ($this->routes as $match => $router) {
            if (preg_match("@^$match@", $url, $matches)) {
                unset($matches[0]);
                self::$matchedArguments = $matches + self::$matchedArguments;
                if ($router instanceof State && preg_match("@^$match$@", $url)) {
                    $router->pipeUnshift(...$pipeline);
                    return $router(self::$matchedArguments, $request);
                } elseif ($router instanceof State) {
                    continue;
                }
                $rootState = $router->getRootState();
                if ($res = $router($request, $rootState ? $rootState->getPipeline() : [])) {
                    return $res;
                }
            }
        }
        return null;
    }

    /**
     * Get the state object identified by $name. This can be used for further
     * processing before control is relinquished.
     *
     * @param string $name The name of the state to query.
     * @return Reroute\State
     * @throws DomainException if the state is not known.
     */
    public function get(string $name)
    {
        if (!isset(self::$namedStates[$name])) {
            throw new DomainException("Unknown state: $name");
        }
        return self::$namedStates[$name];
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
    public function generate(string $name, array $arguments = [], bool $shortest = true) : string
    {
        if (!isset(self::$namedStates[$name])) {
            throw new DomainException("Unknown state: $name");
        }
        $state = self::$namedStates[$name];
        $url = $state->getUrl();
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
        $url = str_replace('\\', '', $url);
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
    protected function normalize(string $url, string $scheme = 'http', string $host = 'localhost') : string
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

    /**
     * Reset the "global" router. Normally, routes are resolved exactly once
     * during a page load, but in some scenarios (testing springs to mind!) you
     * might want to do this multiple times and avoid cruft.
     *
     * @return void
     */
    public static function reset() : void
    {
        self::$namedStates = [];
        self::$matchedArguments = [];
    }
}

