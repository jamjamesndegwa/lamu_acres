<?php

namespace App\Services\Staff;

use App\Enums\EnquiryStatus;
use App\Enums\LeadStatus;
use App\Events\EnquiryAssigned;
use App\Events\EnquiryClosed;
use App\Events\EnquiryResponded;
use App\Models\Enquiry;
use App\Models\EnquiryResponse;
use App\Models\User;

class EnquiryDesk
{
    public function assign(Enquiry $enquiry, User $assignee): Enquiry
    {
        if ((int) $enquiry->assigned_to === (int) $assignee->id) {
            return $enquiry;
        }
        $enquiry->assigned_to = $assignee->id;

        if ($enquiry->status === EnquiryStatus::New) {
            $enquiry->status = EnquiryStatus::Acknowledged;
        }

        $enquiry->save();

        event(new EnquiryAssigned($enquiry));

        return $enquiry;
    }

    public function updateStatus(Enquiry $enquiry, EnquiryStatus $status, LeadStatus $leadStatus): Enquiry
    {
        $wasClosed = $enquiry->status === EnquiryStatus::Closed || $enquiry->lead_status === LeadStatus::ClosedLost;

        $enquiry->status = $status;
        $enquiry->lead_status = $leadStatus;
        $enquiry->closed_at = $status === EnquiryStatus::Closed ? ($enquiry->closed_at ?? now()) : null;
        $enquiry->save();

        $isClosed = $status === EnquiryStatus::Closed || $leadStatus === LeadStatus::ClosedLost;

        if ($isClosed && ! $wasClosed) {
            event(new EnquiryClosed($enquiry));
        }

        return $enquiry;
    }

    public function respond(Enquiry $enquiry, User $author, string $body, bool $visibleToBuyer): EnquiryResponse
    {
        $response = $enquiry->responses()->create([
            'user_id' => $author->id,
            'body' => trim($body),
            'visible_to_buyer' => $visibleToBuyer,
        ]);

        if ($visibleToBuyer) {
            $enquiry->status = EnquiryStatus::Responded;
            $enquiry->responded_at = now();
            $enquiry->save();

            event(new EnquiryResponded($response));
        }

        return $response;
    }
}
