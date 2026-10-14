<?php

namespace App\Services\Payments;

class StkAccepted
{
    public function __construct(
        public string $checkoutRequestId,
        public string $merchantRequestId,
    ) {}
}
