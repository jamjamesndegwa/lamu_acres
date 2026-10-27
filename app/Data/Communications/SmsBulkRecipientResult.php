<?php

namespace App\Data\Communications;

class SmsBulkRecipientResult
{
    public function __construct(
        public bool $success,
        public ?string $messageId = null,
        public ?string $status = null,
        public ?string $error = null,
        public ?float $cost = null,
        public ?int $segments = null,
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
        ], fn ($value): bool => $value !== null);
    }
}
