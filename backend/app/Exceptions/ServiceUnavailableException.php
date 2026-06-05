<?php

namespace App\Exceptions;

use RuntimeException;

class ServiceUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Service temporarily unavailable.')
    {
        parent::__construct($message);
    }
}
