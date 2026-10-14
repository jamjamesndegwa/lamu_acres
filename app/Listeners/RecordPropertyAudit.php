<?php

namespace App\Listeners;

use App\Events\PropertyInventoryChanged;
use App\Models\PropertyAudit;

class RecordPropertyAudit
{
    public function handle(PropertyInventoryChanged $event): void
    {
        PropertyAudit::query()->create([
            'property_id' => $event->property->id,
            'actor_id' => $event->actorId,
            'action' => $event->action,
            'from_status' => $event->fromStatus,
            'to_status' => $event->toStatus,
            'changes' => $event->changes,
        ]);
    }
}
