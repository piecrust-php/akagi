<?php

namespace Akagi;

use InvalidArgumentException;
use RuntimeException;

use League\Flysystem\AdapterInterface as Adapter;
use League\Flysystem\MountManager;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

/**
 * A class responsible for handling static files served by the server.
 */
class VirtualFileSystem
{
    protected $mounts;

    protected $manager;

    /**
     * Builds a new instance
     *
     * @arg The documentroot to use. If not given the current working dir is used.
     */
    public function __construct(Adapter $rootAdapter = null)
    {
        if($rootAdapter === null) {
            $root = getcwd();
            if($root === FALSE) {
                throw new RuntimeException("Can not determine a valid document root.");
            }
            $root = realpath($root);
            if($root === FALSE) {
                throw new RuntimeException("Working directory has no realpath.");
            }
            $rootAdapter = new Local($root);
        }
        $this->manager = new MountManager(array(
             'root' => new Filesystem($rootAdapter)
        ));
        $this->mounts = array('/' => 'root');
    }

    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Gets the root directory for the static files.
     */
    public function getMountedFilesystem($root = '/')
    {
        if(!isset($this->mounts[$root])) {
            throw new InvalidArgumentException("There is no filesystem mounted as '$root'.");
        }
        $protocol = $this->mounts[$root];
        return $this->manager->getFilesystem($protocol);
    }

    /**
     * Adds a virtual mount point to the file system.
     */
    public function addMountPoint(Adapter $adapter, $alias, $protocol)
    {
        $trimmed = rtrim($alias, '/\\');
        if(strlen($trimmed) === 0) {
            throw new InvalidArgumentException("Invalid mountpoint '$trimmed' used.");
        }
        if(isset($this->mounts[$alias])) {
            throw new InvalidArgumentException("The mountpoint $alias is already used.");
        }
        $this->manager->mountFilesystem($protocol, new Filesystem($adapter));
        $this->mounts[$alias] = $protocol;
    }

    public function getDocumentPath($path)
    {
        if($path === '') {
            $path = '/';
        }
        if(isset($this->mounts[$path])) {
            return $this->mounts[$path];
        }

        $trimmed = ltrim($path,'/');
        $parts = explode('/',$trimmed);
        $key = '';
        foreach($parts as $part) {
            $key = "$key/$part";
            if(isset($this->mounts[$key])) {
                $rest = substr($path,strlen($key));
                return $this->mounts[$key] . "://" . $rest;
            }
        }
        return $this->mounts['/'] . "://" . $path;
    }
}

