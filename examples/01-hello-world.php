<?php

require __DIR__ . '/../vendor/autoload.php';

use Akagi\Server;
use Apix\Log\Logger\Stream as Logger;
use React\Http\Response;

$logger = new Logger('php://stderr', 'a');
$server = new Server([], $logger);

$server->routePattern('GET','.*',function ($request,$matches=[]) {
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Hello world\n"
    );
});


$logger->info("Starting up...");
$server->run();
