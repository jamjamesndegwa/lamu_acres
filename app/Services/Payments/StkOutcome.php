<?php

namespace App\Services\Payments;

class StkOutcome
{
    public function __construct(
        public bool $paid,
        public bool $failed,
        public bool $timedOut,
        public ?int $resultCode,
        public ?string $receipt,
        public ?int $amount,
        public ?string $description,
    ) {}

    public static function pending(): self
    {
        return new self(false, false, false, null, null, null, 'Awaiting the handset result.');
    }

    public static function paid(string $receipt, int $amount): self
    {
        return new self(true, false, false, 0, $receipt, $amount, 'The payment is confirmed.');
    }

    public static function failed(int $code, string $description): self
    {
        return new self(false, true, false, $code, null, null, $description);
    }

    public static function timedOut(): self
    {
        return new self(false, false, true, 1037, null, null, 'The handset did not respond in time.');
    }
}
