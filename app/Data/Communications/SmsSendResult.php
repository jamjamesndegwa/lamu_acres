<?php

namespace App\Data\Communications;

class SmsSendResult
{
    public function __construct(
        public string $messageId,
        public ?string $status = null,
        public ?float $cost = null,
        public ?int $segments = null,
        public ?float $balance = null,
        public bool $dryRun = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return array_filter([
            'status' => $this->status,
            'cost' => $this->cost,
            'segments' => $this->segments,
            'balance' => $this->balance,
            'dry_run' => $this->dryRun ? true : null,
        ], fn ($value): bool => $value !== null);
    }
}
