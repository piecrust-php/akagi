<?php

require __DIR__ . '/../vendor/autoload.php';

use Akagi\Server;
use Akagi\VirtualFileSystem;
use Apix\Log\Logger\Stream as Logger;
use React\Http\Response;

$logger = new Logger('php://stderr', 'a');
$filesystem = new VirtualFileSystem();
$server = new Server(['document_root' => $filesystem], $logger);

$logger->info("Starting up...");
$server->run();
