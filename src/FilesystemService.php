<?php

namespace Akagi;

use Psr\Log\LoggerInterface as Logger;
use Psr\Http\Message\ServerRequestInterface as Request;
use React\Http\Response;
use RingCentral\Psr7;
use League\Flysystem\Memory\MemoryAdapter;
use League\Flysystem\FileNotFoundException;


class FilesystemService
{
    protected $filesystem;

    protected $log;

    protected $directory_template;

    public function __construct(VirtualFileSystem $fs = null, Logger $log)
    {
        if($fs === null) {
            $fs = new VirtualFileSystem(new MemoryAdapter());
        }
        $this->filesystem = $fs;
        $this->log = $log;
        $this->directory_template = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'directory-listing.html');
    }

    public function isDocumentRequest(Request $request)
    {
        $path = $request->getUri()->getPath();
        $documentPath = $this->filesystem->getDocumentPath($path);
        return isset($documentPath);
    }

    public function handleRequest(Request $request, Response &$response = null)
    {
        $documentPath = $this->filesystem->getDocumentPath($request->getUri()->getPath());
        $manager = $this->filesystem->getManager();
$this->log->debug('Serving directory: ' . $documentPath);
        if($request->getMethod() === 'GET') {
            try {
                if($this->filesystem->isDirectory($documentPath)) {
                    // Serve existing directory ...
                    $this->log->debug('Serving directory: ' . $documentPath);
                    $response = $this->serveDirectory($request, $documentPath);
                }
                else {
                    // Serve existing file...
                    $this->log->debug('Serving static file: ' . $documentPath);
                    $response = $this->serveDocument($request, $documentPath);
                }
            }
            catch(FileNotFoundException $e404) {
                // pass
            }
        }
    }

    /**
     * Returns a web response that corresponds to serving a given static file.
     */
    public function serveDocument(Request $request, $documentPath)
    {
        $manager = $this->filesystem->getManager();
        // First, check for timestamp if possible.
        $serverTimestamp = $manager->getTimestamp($documentPath);
        $ifModifiedSince = $request->getHeader('If-Modified-Since');
        if ($ifModifiedSince != null) {
            $clientTimestamp = strtotime($ifModifiedSince);
            if ($clientTimestamp > $serverTimestamp) {
                return new Response(304);
            }
        }

        // ...otherwise, check for similar checksum.
        $documentSize = $manager->getSize($documentPath);
        if ($documentSize == 0) {
            return new Response(200,array('Content-Type' => 'text/plain'),'');
        }
        try {
            $contentsHash = md5(stream_get_contents(
                $manager->readStream($documentPath)));
        }
        catch(\Exception $e) {
            throw new WebException('Error reading file: ' . $documentPath
              . "\n" . $e->getMessage(), 500);
        }
        $ifNoneMatch = $request->getHeader('If-None-Match');
        if ($ifNoneMatch != null) {
            if ($ifNoneMatch == $contentsHash) {
                return new Response(304);
            }
        }

        // ...ok, let's send the file.
        $extension = pathinfo($documentPath, PATHINFO_EXTENSION);
        $mimetype = Psr7\mimetype_from_extension($extension);
        $headers = array(
            'Content-Length' => $documentSize,
            'Content-MD5' => base64_encode($contentsHash),
            'Content-Type' => (isset($mimetype) ? $mimetype : 'text/plain'),
            'ETag' => $contentsHash,
            'Last-Modified' => date("D, d M Y H:i:s T", $serverTimestamp)
        );
        return new Response(200, $headers, $manager->readStream($documentPath));
    }

    public function serveDirectory(Request $request, $documentPath)
    {
        $indexPath = $this->getIndexDocument($documentPath);
        if ($indexPath !== null) {
            // Serve a directory's index file...
            $this->log->debug('Serving static index file: ' . $indexPath);
            return $this->serveDocument($request, $indexPath);
        }
        else {
            // Serve the directory's contents...
            $this->log->debug('Serving directory: ' . $documentPath);
            return $this->_serveDirectory($request, $documentPath);
        }
    }

    /**
     * Returns a web response that corresponds to serving the contents of a
     * given directory.
     */
    public function _serveDirectory(Request $request, $documentPath)
    {
        $manager = $this->filesystem->getManager();
        $headers = array();

        $contents = '<ul>' . PHP_EOL;
        foreach ($manager->listContents($documentPath) as $entry) {
            $contents .= '<li>' . $entry['basename'] . '</li>' . PHP_EOL;
        }
        $contents .= '</ul>' . PHP_EOL;

        $replacements = array(
            '%path%' => $documentPath,
            '%contents%' => $contents
        );

        $body = str_replace(array_keys($replacements), array_values($replacements),
            $this->directory_template);
        $contentsHash = md5($body);
        $headers = array(
            'Content-Length' => strlen($body),
            'Content-MD5' => base64_encode($contentsHash),
            'Content-Type' => 'text/html',
            'ETag' => $contentsHash
        );
        return new Response(200, $headers, $body);
    }

    /**
     * Finds the index document for a given directory (e.g. `index.html`).
     */
    protected function getIndexDocument($path)
    {
        static $indexDocuments = array(
            'index.htm',
            'index.html'
        );
        $path = rtrim($path);
        foreach ($indexDocuments as $doc) {
            try {
                $indexfile = $path . "/$doc";
                if(! $this->filesystem->isDirectory($indexfile)) {
                    return $indexfile;
                }
            }
            catch(FileNotFoundException $e) {
                // pass
            }
        }
        return null;
    }
}
