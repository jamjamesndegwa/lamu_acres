<?php

namespace App\Exceptions\Communications;

use RuntimeException;

class SmsUncertainDeliveryException extends RuntimeException
{
    public function __construct(string $message = 'SMS delivery outcome is uncertain.')
    {
        parent::__construct($message);
    }
}
