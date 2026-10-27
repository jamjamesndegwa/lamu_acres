<?php

namespace App\Services\Communications;

use App\Enums\MessageEvent;
use App\Models\User;
use Illuminate\Notifications\Notification;

class StaffDeskNotifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notifyPermission(
        string $permission,
        MessageEvent $event,
        array $context,
        string $subjectKey,
        ?Notification $inAppNotification = null,
        ?int $exceptUserId = null,
    ): void {
        $desk = app(MessageDesk::class);

        User::permission($permission)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->each(function (User $staff) use ($desk, $event, $context, $subjectKey, $inAppNotification): void {
                $personalized = array_merge($context, ['name' => $staff->name]);
                $desk->send(
                    $staff,
                    $event,
                    $personalized,
                    $subjectKey.':staff:'.$staff->id,
                    $inAppNotification,
                );
            });
    }
}
