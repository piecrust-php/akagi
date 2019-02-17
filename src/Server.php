<?php

namespace Akagi;

use Exception;
use Psr\Log\LoggerInterface as Logger;
use Psr\Http\Message\ServerRequestInterface;

use React\EventLoop\Factory as EventLoop;
use React\Http\Response;
use React\Http\Server as ReactHTTP;
use React\Socket\Server as Socket;
use React\Socket\ConnectionInterface as Connection;

class Server
{
    protected $options;

    protected $log;

    protected $requestHandlers;

    protected $fsservice;

    protected $before;

    public function __construct(array $options, Logger $log)
    {
        $defaults = array(
            'name' => 'AKAGI SERVER V0.1',
            'port' => 8080,
            'show_banner' => true,
            'document_root' => null
        );
        $this->options = array_merge($defaults,$options);
        $this->log = $log;
        $this->before = array();
        $this->fsservice = new FilesystemService($this->options['document_root'],$log);
    }

    /**
     * Destructor for the Server.
     */
    public function __destruct()
    {
        $this->log->info("Shutting server down...");
        $this->log = null;
    }

    public function onBefore(callable $callback)
    {
        $this->before[] = $callback;
    }

    /**
     * Runs the server.
     */
    public function run()
    {
        try {
            $this->runUnsafe();
        }
        catch (Exception $e) {
            $this->log->critical($e->getMessage());
            $this->log->critical("The server will now shut down!");
        }
    }

    protected function runUnsafe()
    {
        $loop = EventLoop::create();
        $server = $this;

        $httpd = new ReactHTTP(function (ServerRequestInterface $request)
            use ($server) {
                $this->log->debug("Handle {$request->getUri()}");
                foreach($server->before as $action) {
                    $action($request);
                }

                $response = null;

                try {
                    $responsebuilder = new ResponseBuilder(
                        $server->requestHandlers,
                        $this->log
                    );

                    $responsebuilder->handleRequest($request,$response);
                    if($response) {
                        return $response;
                    }

                    if($this->fsservice->isDocumentRequest($request)) {
                        $this->fsservice->handleRequest($request,$response);
                        if($response) {
                            return $response;
                        }
                    }

                    return new Response(404);
                }
                catch(\Exception $e) {
                    $this->log->error("Error: {$e->getMessage()}");
                    return new Response(500);
                }

                $this->log->debug("Handle 2 {$request->getUri()}");

        });

        $socket = new Socket(8080, $loop);
        $socket->on('connection', function (Connection $con) use ($server) {
            $server->log->debug("Incoming connection from {$con->getRemoteAddress()}.");
        });
        $httpd->on('error',function ($error) use ($server) {
            $server->log->error($error->getMessage());
        });
        $httpd->listen($socket);

        $this->showBanner($socket);
        $loop->run();
    }

    protected function showBanner($socket)
    {
        if ($this->options['show_banner']) {
            if ($this->options['name'] !== null) {
                $this->log->info(">> " . $this->options['name']);
            }
            $this->log->info(">> Listening on {$socket->getAddress()}...");
            $this->log->info(">> (use CTRL+C to stop)");
        }
        else {
            $this->log->debug(">> Started server on {$socket->getAddress()}.");
        }
    }

    /**
     * Adds a request handler.
     */
    protected function addRequestHandler($method, $handler)
    {
        $method = strtoupper($method);
        if (!isset($this->requestHandlers[$method])) {
            $this->requestHandlers[$method] = array();
        }
        $this->requestHandlers[$method][] = $handler;
    }

    /**
     * Adds a route to match requests against, and returns the handler.
     */
    public function route($method, $uri, $callback)
    {
        $uri = '/' . trim($uri, '/');
        $uriPattern = '^' . preg_quote($uri, '|') . '$';
        $handler = $this->routePattern($method, $uriPattern, $callback);
        $handler->setIdentifier("{$method}_{$uri}");
        return $handler;
    }

    /**
     * Adds a route pattern to match requests against, and returns the handler.
     */
    public function routePattern($method, $uriPattern,$callback)
    {
        $handler = new RequestHandler("{$method}_{$uriPattern}", $uriPattern, $callback);
        $this->addRequestHandler($method, $handler);
        return $handler;
    }

    /**
     * Mounts a directory into the document root.
     */
    public function mount($directory, $alias)
    {
        $this->vfs->addMountPoint($directory, $alias);
    }

}
