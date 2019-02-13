<?php

use Apix\Log\Logger\Stream as Logger;
use Apix\Log\Logger\Nil as Nil;

use Akagi\VirtualFileSystem;
use Akagi\FilesystemService;

use React\Http\Io\ServerRequest;
use League\Flysystem\Memory\MemoryAdapter;


class FilesystemServiceTest extends React\Tests\Http\TestCase
{
    use \phpmock\phpunit\PHPMock;

    /**
     * @beforeClass
     */
    public static function setupGetcwdMock()
    {
        self::defineFunctionMock('\\League\\Flysystem\\Memory', "time");
    }

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

    /**
     * @runInSeparateProcessX
     */
    public function testDocumentRequests()
    {
        $realpath = $this->getFunctionMock('\\League\\Flysystem\\Memory', 'time');
        $realpath->expects($this->once())->willReturn('1587878765');

        $logger = new Nil();
        $fs = new VirtualFileSystem(new MemoryAdapter());
        $service = new FilesystemService($fs, $logger);

        $fs->getManager()->write('root:///index.html','<html><body>Hallo</body></html>');

        $request = new ServerRequest('GET', 'http://localhost/index.html');
        $service->handleRequest($request,$response);

        $this->assertEquals($response->getStatuscode(),200);
        $this->assertEquals(current($response->getHeader('content-length')),31);
        $this->assertEquals(current($response->getHeader('content-md5')),'NjQxODE4YzUyZjFjZjNlOWUwYjA4MjkzMDNlZTUwMDU=');
        $this->assertEquals(current($response->getHeader('content-type')),'text/html');
        $this->assertEquals(current($response->getHeader('etag')),'641818c52f1cf3e9e0b0829303ee5005');
        $this->assertEquals(current($response->getHeader('last-modified')),'Sun, 26 Apr 2020 05:26:05 UTC');
        $this->assertEquals(count($response->getHeaders()),5);
    }

    public function testDirectoryRequest()
    {
        $logger = new Nil();
        $fs = new VirtualFileSystem(new MemoryAdapter());
        $service = new FilesystemService($fs, $logger);

        $fs->getManager()->write('root:///liebe/finden.txt','zieht durch den Fluss ...');

        $request = new ServerRequest('GET', 'http://localhost/liebe/');
        $service->handleRequest($request,$response);
        $this->assertEquals(200,$response->getStatuscode());
        $this->assertGreaterThan(0,current($response->getHeader('content-length')));
    }


}
