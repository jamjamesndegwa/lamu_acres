<?php

namespace App\Contracts\Payments;

use App\Services\Payments\StkAccepted;
use App\Services\Payments\StkOutcome;

interface MpesaGateway
{
    public function push(int $amount, string $phone, string $reference): StkAccepted;

    public function query(string $checkoutRequestId): StkOutcome;
}
