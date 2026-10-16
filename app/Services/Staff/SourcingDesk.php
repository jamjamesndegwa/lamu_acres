<?php

namespace App\Services\Staff;

use App\Enums\SourcingRequestStatus;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Notifications\SourcingAssignedNotification;
use App\Notifications\SourcingUpdatedNotification;

class SourcingDesk
{
    public function assign(SourcingRequest $request, User $assignee, User $actor): SourcingRequest
    {
        if ((int) $request->assigned_to === (int) $assignee->id) {
            return $request;
        }
        $request->assigned_to = $assignee->id;

        if ($request->status === SourcingRequestStatus::Submitted) {
            $request->status = SourcingRequestStatus::InProgress;
        }

        $request->save();
        $request->activities()->create([
            'actor_id' => $actor->id,
            'summary' => 'Assigned to '.$assignee->name.'.',
            'visible_to_buyer' => true,
        ]);
        $assignee->notify(new SourcingAssignedNotification($request));

        return $request;
    }

    public function updateStatus(SourcingRequest $request, SourcingRequestStatus $status, User $actor): SourcingRequest
    {
        if ($request->status === $status) {
            return $request;
        }
        $request->status = $status;
        $request->closed_at = $status === SourcingRequestStatus::Closed ? ($request->closed_at ?? now()) : null;
        $request->save();
        $request->activities()->create([
            'actor_id' => $actor->id,
            'summary' => 'Status set to '.$status->label().'.',
            'visible_to_buyer' => true,
        ]);

        $request->buyer?->notify(new SourcingUpdatedNotification($request));

        return $request;
    }
}
