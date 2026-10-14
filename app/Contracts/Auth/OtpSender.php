<?php

namespace App\Contracts\Auth;

use App\Services\Auth\OtpDeliveryResult;

interface OtpSender
{
    public function available(): bool;

    /**
     * Hand a one-time code to a real delivery provider.
     * Return delivered=false when the provider did not accept the message.
     */
    public function send(string $phone, string $code): OtpDeliveryResult;
}
