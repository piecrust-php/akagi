<?php

use React\Http\Io\RequestHeaderParser;

class WebRequestTest extends React\Tests\Http\TestCase
{
    public function methodUriVersionParsingDataProvider()
    {
        return array(
            array(
                'GET /index.html HTTP/1.1',
                'GET',
                '/index.html',
                '1.1'
            ),
            array(
                'POST /postback/something.aspx?mode=1 HTTP/1.1',
                'POST',
                '/postback/something.aspx',
                '1.1'
            ),
            array(
                'GET /old/stuff HTTP/1.0',
                'GET',
                '/old/stuff',
                '1.0'
            ),
            array(
                'GET /directory/ HTTP/1.1',
                'GET',
                '/directory/',
                '1.1'
            ),
        );
    }

    /**
     * @dataProvider methodUriVersionParsingDataProvider
     */
    public function testMethodUriVersionParsing($raw, $method, $uri, $version)
    {
        $request = null;
        $bodyBuffer = null;
        $error = null;
        $passedParser = null;

        $parser = new RequestHeaderParser;
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer)
            use (&$request, &$bodyBuffer) {
            $request = $parsedRequest;
            $bodyBuffer = $parsedBodyBuffer;
        });
        $parser->on('error', function ($message, $parser)
            use (&$error, &$passedParser) {
            $error = $message;
            $passedParser = $parser;
        });

        $parser->feed($raw);
        $parser->feed("\r\n");
        $parser->feed("Host: example.com:80\r\n");
        $parser->feed("Connection: close\r\n");
        $parser->feed("\r\n");
        $parser->feed('RRRRROOOO');

        $this->assertEquals($method, $request->getMethod());
        $this->assertEquals($uri, $request->getUri()->getPath());
        $this->assertEquals($version, $request->getProtocolVersion());
    }

    public function requestParsingDataProvider()
    {
        return array(
            array(
                "GET /index.html HTTP/1.1\r\n",
                array()
            ),
            array(
                "GET /index.html HTTP/1.1\r\n".
                "Blah: something\r\n",
                array(
                    'Blah' => ['something']
                )
            ),
            array(
                "GET /index.html HTTP/1.1\r\n".
                "Blah: something\r\n".
                "Foo: bar-bar\r\n",
                array(
                    'Blah' => ['something'],
                    'Foo' => ['bar-bar']
                )
            ),
            array(
                "GET /index.html HTTP/1.1\r\n".
                "Content-Type: text/html\r\n".
                "Content-MD5: Q2hlY2sgSW50ZWdyaXR5IQ==\r\n".
                "From: user@example.org\r\n",
                array(
                    'Content-Type' => ['text/html'],
                    'Content-MD5' => ['Q2hlY2sgSW50ZWdyaXR5IQ=='],
                    'From' => ['user@example.org']
                )
            )
        );
    }

    /**
     * @dataProvider requestParsingDataProvider
     */
    public function testRequestParsing($raw, $headers)
    {
        $request = null;
        $bodyBuffer = null;

        $parser = new RequestHeaderParser;
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer)
            use (&$request, &$bodyBuffer) {
            $request = $parsedRequest;
            $bodyBuffer = $parsedBodyBuffer;
        });

        $parser->feed($raw);
        $parser->feed("\r\n");
        $parser->feed('RRRRROOOO');
        $headers['Host'] = ['127.0.0.1'];

        $this->assertEquals($headers, $request->getHeaders());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEmptyRequest()
    {
        $parser = new RequestHeaderParser;
        $parser->on('error', function ($message) {
            throw $message;
        });

        $parser->feed("\r\n\r\n");
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidRequest()
    {
        $request = null;
        $bodyBuffer = null;

        $parser = new RequestHeaderParser;
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer)
            use (&$request, &$bodyBuffer) {
            $request = $parsedRequest;
            $bodyBuffer = $parsedBodyBuffer;
        });
        $parser->on('error', function ($message) {
            throw $message;
        });

        $parser->feed("GET something HPPT/1.0\r\n");
        $parser->feed("\r\n");

    }
}
