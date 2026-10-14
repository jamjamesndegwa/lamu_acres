<?php

namespace App\Events;

use App\Models\Property;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyInventoryChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public Property $property,
        public ?int $actorId,
        public string $action,
        public ?string $fromStatus,
        public ?string $toStatus,
        public array $changes = [],
    ) {}
}
