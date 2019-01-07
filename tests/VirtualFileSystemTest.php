<?php

use Apix\Log\Logger\Stream as Logger;
use Akagi\VirtualFileSystem;

class VirtualFileSystemTest extends React\Tests\Http\TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNotADirectory()
    {
        $logger = new Logger('php://stderr', 'a');
        $vfs = new VirtualFileSystem('/uuu/hhh/ggg',$logger);
    }
}
