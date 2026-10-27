<?php

namespace App\Services\Communications;

use App\Contracts\Communications\SmsSender;
use App\Data\Communications\SmsSendResult;
use RuntimeException;

class UnconfiguredSmsSender implements SmsSender
{
    public function configured(): bool
    {
        return false;
    }

    public function send(string $phone, string $body): SmsSendResult
    {
        throw new RuntimeException('SMS provider is not configured.');
    }

    public function sendBulk(array $messages): array
    {
        throw new RuntimeException('SMS provider is not configured.');
    }
}
