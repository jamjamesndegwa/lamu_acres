<?php

namespace App\Services\Auth;

use App\Contracts\Auth\OtpSender;

class UnconfiguredOtpSender implements OtpSender
{
    public function available(): bool
    {
        return false;
    }

    public function send(string $phone, string $code): OtpDeliveryResult
    {
        return new OtpDeliveryResult(delivered: false, provider: 'unconfigured');
    }
}
