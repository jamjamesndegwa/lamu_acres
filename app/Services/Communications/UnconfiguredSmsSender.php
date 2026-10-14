<?php

namespace App\Services\Communications;

use App\Contracts\Communications\SmsSender;
use RuntimeException;

class UnconfiguredSmsSender implements SmsSender
{
    public function configured(): bool
    {
        return false;
    }

    public function send(string $phone, string $body): void
    {
        throw new RuntimeException('SMS provider is not configured.');
    }
}
