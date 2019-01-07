<?php

namespace Akagi;

use Exception;
use Psr\Log\LoggerInterface as Logger;

use React\Http\Response;

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

    public function run($request)
    {
        try {
            return $this->runUnsafe($request);
        }
        catch(Exception $error) {
            $this->log->error("Error handling request: {$error->getMessage()}");
            return new Response(500);
        }
    }
    /**
     * Runs the builder, and returns the web response.
     */
    public function runUnsafe($request)
    {
        $method = $request->getMethod();
        if (isset($this->handlers[$method])) {
            foreach ($this->handlers[$method] as $handler) {
                if ($handler->isMatch($request->getUri()->getPath())) {
                    try {
                        return $handler->run($request);
                    }
                    catch(Exception $error) {
                        $this->log->error("Error in handler {$handler->getIdentifier()}:" .
                            "{$error->getMessage()}");
                        return Response(500);
                    }
                }
            }
        }
        if ($request->getMethod() == 'GET') {
            return new Response(404);  // Not found.
        }
        else {
            return new Response(501);  // Method not implemented.
        }
    }
}

