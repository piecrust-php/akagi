<?php

use Akagi\VirtualFileSystem;
use PHPUnit\Framework\TestCase;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Memory\MemoryAdapter;

/**
 *
 */
class VirtualFileSystemTest extends TestCase
{
     use \phpmock\phpunit\PHPMock;

     protected static $getcwd;

    /**
     * @beforeClass
     */
    public static function setupGetcwdMock()
    {
        self::defineFunctionMock('\\Akagi', "getcwd");
        self::defineFunctionMock('\\Akagi', "realpath");
    }

    /**
     * @expectedException \League\Flysystem\Exception
     */
    public function testNotADirectory()
    {
        $local = new Local("/uuu/hhh/ggg");
        $vfs = new VirtualFileSystem($local);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testMountRootAgain()
    {
        $vfs = new VirtualFileSystem( );
        $vfs->addMountPoint(new Local('/'),'/','root');
    }

    /**
     * @runInSeparateProcess
     * @expectedException \RuntimeException
     */
    public function testNoGetcwd()
    {
        $getcwd = $this->getFunctionMock('\\Akagi', 'getcwd');
        $getcwd->expects($this->once())->willReturn(false);
        $vfs = new VirtualFileSystem();
    }

    /**
     * @runInSeparateProcess
     * @expectedException \RuntimeException
     */
    public function testNoRealpath()
    {
        $realpath = $this->getFunctionMock('\\Akagi', 'realpath');
        $realpath->expects($this->once())->willReturn(false);
        $vfs = new VirtualFileSystem();
    }

    public function testSimpleVirtualVFS()
    {
        $vfs = new VirtualFileSystem();
        $mount = new MemoryAdapter();
        $vfs->addMountPoint($mount,'/mount','tmpfs01');

        $this->assertInstanceOf('League\\Flysystem\\Filesystem',$vfs->getMountedFilesystem('/'));
        $this->assertInstanceOf('League\\Flysystem\\Filesystem',$vfs->getMountedFilesystem('/mount'));

        $vfs->getManager()->write('tmpfs01:///blues.txt','BLUE NOTE');

        $this->assertEquals('tmpfs01:///blues.txt', $vfs->getDocumentPath('/mount/blues.txt'));
        $this->assertEquals('root:///index.htm',$vfs->getDocumentPath('/index.htm'));

        $this->assertEquals($vfs->getManager()->read('tmpfs01:///blues.txt'),'BLUE NOTE');
    }

    public function testNoDocumentRoot()
    {
        $current = getcwd();
        $vfs = new VirtualFileSystem();

        $this->assertInstanceOf('League\\Flysystem\\Filesystem',$vfs->getMountedFilesystem('/'));
        $adapter = $vfs->getMountedFilesystem('/')->getAdapter();
        $this->assertInstanceOf('League\\Flysystem\\Adapter\\Local',$adapter);

        $this->assertEquals($adapter->getPathPrefix(),realpath($current).'/');
    }

    public function testSimpleMountVirtualVFS()
    {
        $vfs = new VirtualFileSystem();
        $mount1 = new MemoryAdapter();
        $vfs->addMountPoint($mount1,'/mount/tmp01','tmpfs01');
        $mount2 = new MemoryAdapter();
        $vfs->addMountPoint($mount2,'/mount/tmp02','tmpfs02');

        $this->assertEquals('root', $vfs->getDocumentPath(''));
        $this->assertEquals('root', $vfs->getDocumentPath('/'));
        $this->assertEquals('root:///text/hello.txt', $vfs->getDocumentPath('/text/hello.txt'));
        $this->assertEquals('tmpfs01:///index.htm',$vfs->getDocumentPath('/mount/tmp01/index.htm'));
        $this->assertEquals('tmpfs02:///index.htm',$vfs->getDocumentPath('/mount/tmp02/index.htm'));
    }

}
