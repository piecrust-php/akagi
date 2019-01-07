<?php

namespace Akagi;

use Psr\Http\Message\ServerRequestInterface as Request;

class RequestHandler
{
    protected $identifier;
    protected $uriPattern;
    protected $uriPatternMatches;
    protected $callback;

    /**
     * Creates a new WebRequestHandler.
     */
    public function __construct($identifier, $uriPattern, $callback)
    {
        $this->identifier = $identifier;
        $this->uriPattern = $uriPattern;
        $this->uriPatternMatches = array();
        $this->setCallback($callback);
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Specifies the callback to use if a matching HTTP request comes up.
     */
    protected function setCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new WebException('The given callback is not a callable PHP object.');
        }
        $this->callback = $callback;
        return $this;
    }

    /**
     * Check if pattern matches the path.
     */
    public function isMatch($uri)
    {
        return preg_match('|' . $this->uriPattern . '|i', $uri, $this->uriPatternMatches);
    }

    /**
     * Run the callback.
     */
    public function run(Request $request)
    {
        $callback = $this->callback;

        if (count($this->uriPatternMatches) > 1) {
            return $callback($request, $this->uriPatternMatches);
        }
        else {
            return $callback($request);
        }
    }
}
