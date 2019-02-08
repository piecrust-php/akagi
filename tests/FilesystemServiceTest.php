<?php

use Apix\Log\Logger\Stream as Logger;

use Akagi\VirtualFileSystem;
use Akagi\FilesystemService;

use React\Http\Io\ServerRequest;

class FilesystemServiceTest extends React\Tests\Http\TestCase
{
    public function testBasicMethods()
    {
        $logger = new Logger('php://stderr', 'a');
        $vfs = new VirtualFileSystem();

        $service = new FilesystemService($vfs, $logger);

        $request = new ServerRequest('GET', 'http://localhost');
        $this->assertTrue($service->isDocumentRequest($request));

        $request = new ServerRequest('GET', 'http://localhost/hallo.html');
        $this->assertTrue($service->isDocumentRequest($request));
    }


}
