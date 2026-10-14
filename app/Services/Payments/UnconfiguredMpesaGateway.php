<?php

namespace App\Services\Payments;

use App\Contracts\Payments\MpesaGateway;
use Illuminate\Validation\ValidationException;

class UnconfiguredMpesaGateway implements MpesaGateway
{
    public function push(int $amount, string $phone, string $reference): StkAccepted
    {
        throw ValidationException::withMessages([
            'phone' => 'M-Pesa is not configured. No request was sent.',
        ]);
    }

    public function query(string $checkoutRequestId): StkOutcome
    {
        throw ValidationException::withMessages([
            'payment' => 'M-Pesa is not configured. No status request was sent.',
        ]);
    }
}
