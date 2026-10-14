<?php

namespace App\Services\Auth;

readonly class OtpDeliveryResult
{
    public function __construct(
        public bool $delivered,
        public string $provider,
    ) {}
}
