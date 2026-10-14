<?php

namespace App\Services\Payments;

use App\Contracts\Payments\MpesaGateway;

class FakeMpesaGateway implements MpesaGateway
{
    public int $pushes = 0;

    public bool $throwOnQuery = false;

    /** @var array<string, StkOutcome> */
    public array $outcomes = [];

    public function push(int $amount, string $phone, string $reference): StkAccepted
    {
        $this->pushes++;
        $id = 'ws_CO_'.$this->pushes;
        $this->outcomes[$id] ??= StkOutcome::pending();

        return new StkAccepted($id, 'merchant_'.$this->pushes);
    }

    public function query(string $checkoutRequestId): StkOutcome
    {
        if ($this->throwOnQuery) {
            throw new MpesaTimeout('The provider did not answer.');
        }

        return $this->outcomes[$checkoutRequestId] ?? StkOutcome::pending();
    }
}
