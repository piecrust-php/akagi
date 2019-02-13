<?php

namespace Akagi;

use Exception;
use Psr\Log\LoggerInterface as Logger;

use React\Http\Request;
use React\Http\Response;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;

/**
 * A class responsible for building the response to a web request.
 */
class ResponseBuilder
{
    protected $handlers;
    protected $log;

    /**
     * Builds a new instance of StupidHttp_ResponseBuilder.
     */
    public function __construct($handlers, Logger $log)
    {
        $this->handlers = $handlers;
        $this->log = $log;
    }

    public function handleRequest(RequestInterface $request, Response &$response = null)
    {
        try {
            $this->handleUnsafe($request,$response);
        }
        catch(Exception $error) {
            $this->log->error("Error handling request: {$error->getMessage()}");
            $response = new Response(500);
        }
    }
    /**
     * Runs the builder, and returns the web response.
     */
    public function handleUnsafe(RequestInterface $request, Response &$response = null)
    {
        $method = $request->getMethod();
        if (isset($this->handlers[$method])) {
            foreach ($this->handlers[$method] as $handler) {
                if ($handler->isMatch($request->getUri()->getPath())) {
                    try {
                        $response = $handler->run($request);
                    }
                    catch(Exception $error) {
                        $this->log->error("Error in handler {$handler->getIdentifier()}:" .
                            "{$error->getMessage()}");
                        $response = new Response(500);
                    }
                }
            }
        }
    }
}

