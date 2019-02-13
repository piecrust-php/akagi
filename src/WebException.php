<?php

namespace Akagi;

/**
 * The exception class used during the response action.
 */
class WebException extends \Exception
{
    /**
     * Creates a new instance of WebException.
     */
    public function __construct($message = null, $code = 0)
    {
        parent::__construct($message, $code);
    }
}
