<?php

namespace Akagi;

use Psr\Log\LoggerInterface as Logger;
use Psr\Http\Message\ServerRequestInterface as Request;
use React\Http\Response;
use RingCentral\Psr7;
use League\Flysystem\Memory\MemoryAdapter;


class FilesystemService
{
    protected $filesystem;

    protected $log;

    public function __construct(VirtualFileSystem $fs = null, Logger $log)
    {
        if($fs === null) {
            $fs = new VirtualFileSystem(new MemoryAdapter());
        }
        $this->filesystem = $fs;
        $this->log = $log;
    }

    public function isDocumentRequest(Request $request)
    {
        $path = $request->getUri()->getPath();
        $documentPath = $this->filesystem->getDocumentPath($path);
        return isset($documentPath);
    }


    public function handleDocumentRequest($request)
    {
        // See if the request maps to an existing file on our VFS.
        $handled = false;
        $documentPath = $this->getDocumentPath($request->getUri()->getPath());

        if ($documentPath != null) {
            if ($request->getMethod() == 'GET' and is_file($documentPath)) {
                // Serve existing file...
                $this->log->debug('Serving static file: ' . $documentPath);
                return $this->vfs->serveDocument($request, $documentPath);
            }
            else if ($request->getMethod() == 'GET' and is_dir($documentPath)) {
                $indexPath = $this->vfs->getIndexDocument($documentPath);
                if ($indexPath != null) {
                    // Serve a directory's index file...
                    $this->log->debug('Serving static index file: ' . $indexPath);
                    return $this->vfs->serveDocument($request, $indexPath);
                }
                else if (
                    $options['list_directories'] and
                    (
                        $options['list_root_directory'] or
                        $request->getUriPath() != '/'
                    )
                )
                {
                    // Serve the directory's contents...
                    $this->log->debug('Serving directory: ' . $documentPath);
                    return $this->vfs->serveDirectory($request, $documentPath);
                }
            }
        }
    }

    /**
     * Returns a web response that corresponds to serving a given static file.
     */
    public function serveDocument(Request $request, $documentPath)
    {
        // First, check for timestamp if possible.
        $serverTimestamp = filemtime($documentPath);
        $ifModifiedSince = $request->getHeader('If-Modified-Since');
        if ($ifModifiedSince != null) {
            $clientTimestamp = strtotime($ifModifiedSince);
            if ($clientTimestamp > $serverTimestamp) {
                return new Response(304);
            }
        }

        // ...otherwise, check for similar checksum.
        $documentSize = filesize($documentPath);
        if ($documentSize == 0) {
            return new Response(200,array('Content-Type' => 'text/plain'),'');
        }
        $documentHandle = fopen($documentPath, "rb");
        $contents = fread($documentHandle, $documentSize);
        fclose($documentHandle);
        if ($contents === false) {
            throw new WebException('Error reading file: ' . $documentPath, 500);
        }
        $contentsHash = md5($contents);
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
            'Content-Type' => (
                isset($this->mimeTypes[$extension]) ?
                    $this->mimeTypes[$extension] : 'text/plain'
            ),
            'ETag' => $contentsHash,
            'Last-Modified' => date("D, d M Y H:i:s T", filemtime($documentPath))
        );
        return new StupidHttp_WebResponse(200, $headers, $contents);
    }

    /**
     * Returns a web response that corresponds to serving the contents of a
     * given directory.
     */
    public function serveDirectory(Request $request, $documentPath)
    {
        $headers = array();

        $contents = '<ul>' . PHP_EOL;
        foreach (new DirectoryIterator($documentPath) as $entry)
        {
            $contents .= '<li>' . $entry->getFilename() . '</li>' . PHP_EOL;
        }
        $contents .= '</ul>' . PHP_EOL;

        $replacements = array(
            '%path%' => $documentPath,
            '%contents%' => $contents
        );
        $body = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'directory-listing.html');
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        return new StupidHttp_WebResponse(200, $headers, $body);
    }

    /**
     * Finds the index document for a given directory (e.g. `index.html`).
     */
    public function getIndexDocument($path)
    {
        static $indexDocuments = array(
            'index.htm',
            'index.html'
        );
        $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
        foreach ($indexDocuments as $doc) {
            if (is_file($path . $doc)) {
                return $path . $doc;
            }
        }
        return null;
    }
}
