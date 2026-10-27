<?php

namespace App\Exceptions\Communications;

use RuntimeException;

class SmsProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public int $statusCode,
        public bool $retriable = false,
    ) {
        parent::__construct($message);
    }
}
