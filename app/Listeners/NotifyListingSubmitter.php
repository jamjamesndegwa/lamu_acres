<?php

namespace App\Listeners;

use App\Enums\MessageEvent;
use App\Events\PropertyInventoryChanged;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ListingStatusNotification;
use App\Notifications\PropertyApprovedNotification;
use App\Services\Communications\MessageDesk;

class NotifyListingSubmitter
{
    public function handle(PropertyInventoryChanged $event): void
    {
        $property = $event->property;

        if ($event->action === 'portion_sold') {
            $this->notifyInventoryImpact($property);

            return;
        }

        if ($event->action === 'updated' && $this->staffMaterialUpdate($event)) {
            $this->notifyMaterialChange($property, $event);

            return;
        }

        if ($event->action !== 'status_changed' || $event->fromStatus === $event->toStatus || $property->submitter === null) {
            return;
        }

        if ($event->toStatus === 'published') {
            app(MessageDesk::class)->send($property->submitter, MessageEvent::PropertyPublished, [
                'name' => $property->submitter->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => $property->title.' is now published on Lamu Acres.',
                'url' => $property->submitterUrl(),
            ], 'property:'.$property->id.':published:'.($property->published_at?->getTimestamp() ?? '0'), new PropertyApprovedNotification($property));

            return;
        }

        if ($event->fromStatus === 'review' && $event->toStatus === 'draft') {
            app(MessageDesk::class)->send($property->submitter, MessageEvent::ListingRejected, [
                'name' => $property->submitter->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'Staff returned your listing to draft. Open it to make the requested corrections.',
                'url' => $property->submitterUrl(),
            ], 'property:'.$property->id.':rejected:'.($event->fromStatus ?? 'review'), new ListingStatusNotification($property));

            return;
        }

        if ($event->fromStatus === 'published' && in_array($event->toStatus, ['draft', 'withdrawn', 'suspended', 'archived'], true)) {
            app(MessageDesk::class)->send($property->submitter, MessageEvent::PropertyUnpublished, [
                'name' => $property->submitter->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'Your listing is no longer published on Lamu Acres. Open your listing workspace for details.',
                'url' => $property->submitterUrl(),
            ], 'property:'.$property->id.':unpublished:'.$event->toStatus);
        }
    }

    private function notifyInventoryImpact(Property $portion): void
    {
        $parent = $portion->parent_id !== null
            ? Property::query()->with('submitter')->find($portion->parent_id)
            : null;

        if ($parent === null || $parent->submitter === null) {
            return;
        }

        $acreage = (string) ($portion->acreage ?? '');

        app(MessageDesk::class)->send($parent->submitter, MessageEvent::ListingInventoryImpact, [
            'name' => $parent->submitter->name,
            'title' => $parent->title,
            'reference' => $parent->reference,
            'summary' => 'A completed sale affected your listed inventory'.($acreage !== '' ? ' ('.$acreage.' acres).' : '.'),
            'url' => $parent->submitterUrl(),
        ], 'property:'.$parent->id.':portion:'.$portion->id);
    }

    private function notifyMaterialChange(Property $property, PropertyInventoryChanged $event): void
    {
        if ($property->submitter === null) {
            return;
        }

        app(MessageDesk::class)->send($property->submitter, MessageEvent::ListingMaterialChange, [
            'name' => $property->submitter->name,
            'title' => $property->title,
            'reference' => $property->reference,
            'summary' => 'Staff updated material details on your listing. Review the listing workspace for changes.',
            'url' => $property->submitterUrl(),
        ], 'property:'.$property->id.':material:'.md5(json_encode($event->changes)));
    }

    private function staffMaterialUpdate(PropertyInventoryChanged $event): bool
    {
        if ($event->actorId === null || $event->changes === []) {
            return false;
        }

        $property = $event->property;
        $actor = User::query()->find($event->actorId);

        if ($actor === null || ! $actor->hasPermission('staff.properties')) {
            return false;
        }

        if ($property->submitter?->is($actor)) {
            return false;
        }

        return true;
    }
}
