<?php

namespace App\Services\Communications;

use App\Enums\LeadStatus;
use App\Enums\MessageEvent;
use App\Enums\SiteVisitStatus;
use App\Models\DocumentAccessRequest;
use App\Models\Enquiry;
use App\Models\EnquiryResponse;
use App\Models\Property;
use App\Models\SiteVisit;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Notifications\DocumentAccessDecidedNotification;
use App\Notifications\EnquiryResponseNotification;
use App\Notifications\EnquirySubmittedNotification;
use App\Notifications\SiteVisitUpdatedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class BuyerInterestNotifier
{
    public function __construct(private PropertyInterestRecipients $recipients) {}

    public function enquirySubmitted(Enquiry $enquiry): void
    {
        $enquiry->loadMissing(['buyer', 'property', 'salesTransaction']);
        $property = $enquiry->property;
        $buyer = $enquiry->buyer;

        if ($property === null || $buyer === null) {
            return;
        }

        if ($enquiry->purchase_request) {
            $this->purchaseRequestSubmitted($enquiry);

            return;
        }

        $this->desk(
            $buyer,
            MessageEvent::BuyerEnquiryAcknowledged,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'We received your enquiry about '.$property->title.'. Our team will review it and update you in your buyer workspace.',
                'url' => route('buyer.enquiries.show', $enquiry),
            ],
            'enquiry:'.$enquiry->id.':buyer:ack',
        );

        foreach ($this->staffForEnquiry($enquiry) as $staff) {
            $this->desk(
                $staff,
                MessageEvent::BuyerEnquiry,
                [
                    'name' => $staff->name,
                    'title' => $property->title,
                    'reference' => $property->reference,
                    'summary' => 'A buyer asked about '.$property->title.'.',
                    'url' => route('staff.enquiries.show', $enquiry),
                ],
                'enquiry:'.$enquiry->id.':staff:'.$staff->id,
                new EnquirySubmittedNotification($enquiry),
            );
        }

        $this->notifySellers(
            $property,
            'A prospective buyer submitted an enquiry concerning your property, '.$property->title.'. Our team is reviewing the request.',
            'enquiry:'.$enquiry->id.':interest',
            route('seller.enquiries.index'),
        );

        $this->notifyListingBroker(
            $property,
            'A buyer submitted an enquiry about '.$property->title.'.',
            'enquiry:'.$enquiry->id.':broker',
        );
    }

    public function purchaseRequestSubmitted(Enquiry $enquiry): void
    {
        $enquiry->loadMissing(['buyer', 'property', 'salesTransaction']);
        $property = $enquiry->property;
        $buyer = $enquiry->buyer;

        if ($property === null || $buyer === null) {
            return;
        }

        $this->desk(
            $buyer,
            MessageEvent::PurchaseRequestAcknowledged,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'We received your request to purchase '.$property->title.'. Staff will review it; this is not a completed sale.',
                'url' => route('buyer.enquiries.show', $enquiry),
            ],
            'enquiry:'.$enquiry->id.':purchase:buyer:ack',
        );

        foreach ($this->staffForEnquiry($enquiry) as $staff) {
            $this->desk(
                $staff,
                MessageEvent::PurchaseRequestStaffAlert,
                [
                    'name' => $staff->name,
                    'title' => $property->title,
                    'reference' => $property->reference,
                    'summary' => 'A buyer submitted a formal purchase request for '.$property->title.'.',
                    'url' => route('staff.enquiries.show', $enquiry),
                ],
                'enquiry:'.$enquiry->id.':purchase:staff:'.$staff->id,
                new EnquirySubmittedNotification($enquiry),
            );
        }

        $this->notifySellers(
            $property,
            'A buyer submitted a formal purchase request concerning '.$property->title.'. Staff are reviewing it; no sale is completed yet.',
            'enquiry:'.$enquiry->id.':purchase:interest',
            route('seller.enquiries.index'),
        );

        $this->notifyListingBroker(
            $property,
            'A buyer submitted a purchase request for '.$property->title.'.',
            'enquiry:'.$enquiry->id.':purchase:broker',
        );
    }

    public function enquiryResponded(EnquiryResponse $response): void
    {
        if (! $response->visible_to_buyer) {
            return;
        }

        $response->loadMissing('enquiry.buyer', 'enquiry.property');
        $buyer = $response->enquiry?->buyer;
        $property = $response->enquiry?->property;

        if ($buyer === null || $property === null) {
            return;
        }

        $this->desk(
            $buyer,
            MessageEvent::EnquiryResponseReceived,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'Staff responded to your enquiry about '.$property->title.'. Open your enquiry workspace to read the update.',
                'url' => route('buyer.enquiries.show', $response->enquiry),
            ],
            'enquiry:'.$response->enquiry_id.':response:'.$response->id,
            new EnquiryResponseNotification($response),
        );
    }

    public function enquiryClosed(Enquiry $enquiry): void
    {
        $enquiry->loadMissing('buyer', 'property');
        $buyer = $enquiry->buyer;
        $property = $enquiry->property;

        if ($buyer === null || $property === null) {
            return;
        }

        $summary = $enquiry->lead_status === LeadStatus::ClosedLost
            ? 'Your enquiry about '.$property->title.' was closed. Open your enquiries workspace for the latest status.'
            : 'Your enquiry about '.$property->title.' was closed by the desk.';

        $this->desk(
            $buyer,
            MessageEvent::EnquiryClosedBuyer,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => $summary,
                'url' => route('buyer.enquiries.show', $enquiry),
            ],
            'enquiry:'.$enquiry->id.':closed:'.$enquiry->status->value.':'.$enquiry->lead_status->value,
        );
    }

    public function sourcingRequestSubmitted(SourcingRequest $request): void
    {
        $request->loadMissing('buyer');
        $buyer = $request->buyer;

        if ($buyer === null) {
            return;
        }

        $this->desk(
            $buyer,
            MessageEvent::SourcingRequestAcknowledged,
            [
                'name' => $buyer->name,
                'title' => $request->reference,
                'reference' => $request->reference,
                'summary' => 'We received your land sourcing request '.$request->reference.'. Staff will review it and update you in your buyer workspace.',
                'url' => route('buyer.sourcing.show', $request),
            ],
            'sourcing:'.$request->id.':buyer:ack',
        );

        $staff = User::permission('staff.sourcing')->orderBy('id')->first();

        if ($staff !== null) {
            $this->desk(
                $staff,
                MessageEvent::SourcingStaffAlert,
                [
                    'name' => $staff->name,
                    'title' => $request->reference,
                    'reference' => $request->reference,
                    'summary' => 'A buyer submitted sourcing request '.$request->reference.'.',
                    'url' => route('staff.sourcing.show', $request),
                ],
                'sourcing:'.$request->id.':staff:'.$staff->id,
            );
        }
    }

    public function documentAccessRequested(DocumentAccessRequest $request): void
    {
        $request->loadMissing('buyer', 'property');
        $buyer = $request->buyer;
        $property = $request->property;

        if ($buyer === null || $property === null) {
            return;
        }

        $request->loadMissing('match');
        $matchUrl = $request->match !== null
            ? route('buyer.matches.show', $request->match)
            : route('buyer.sourcing.index');

        $this->desk(
            $buyer,
            MessageEvent::DocumentAccessAcknowledged,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'We received your document access request for '.$property->title.'. Staff will review it before anything is shared.',
                'url' => $matchUrl,
            ],
            'document:'.$request->id.':buyer:ack',
        );

        $staff = User::permission('staff.buyers')->orderBy('id')->first();

        if ($staff !== null) {
            $this->desk(
                $staff,
                MessageEvent::DocumentAccessStaffAlert,
                [
                    'name' => $staff->name,
                    'title' => $property->title,
                    'reference' => $property->reference,
                    'summary' => 'A buyer requested document access for '.$property->title.'.',
                    'url' => route('staff.documents.index'),
                ],
                'document:'.$request->id.':staff:'.$staff->id,
                new DocumentAccessDecidedNotification($request, 'A buyer requested document access.'),
            );
        }
    }

    public function siteVisitRequested(SiteVisit $visit): void
    {
        $visit->loadMissing(['buyer', 'property']);
        $property = $visit->property;
        $buyer = $visit->buyer;

        if ($property === null || $buyer === null) {
            return;
        }

        $this->desk(
            $buyer,
            MessageEvent::SiteVisitRequestedAcknowledged,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'We received your viewing request for '.$property->title.'. Staff will propose a time; it is not confirmed yet.',
                'url' => route('buyer.visits.index'),
            ],
            'visit:'.$visit->id.':requested:buyer',
        );

        foreach ($this->staffForVisit($visit) as $staff) {
            $this->desk(
                $staff,
                MessageEvent::SiteVisitStaffAlert,
                [
                    'name' => $staff->name,
                    'title' => $property->title,
                    'reference' => $property->reference,
                    'summary' => 'A buyer requested a site visit for '.$property->title.'.',
                    'url' => route('staff.visits.index'),
                ],
                'visit:'.$visit->id.':requested:staff:'.$staff->id,
                new SiteVisitUpdatedNotification($visit, 'A buyer requested a site visit.'),
            );
        }

        $this->notifySellers(
            $property,
            'A buyer requested a viewing for '.$property->title.'. Staff will coordinate the appointment.',
            'visit:'.$visit->id.':requested:interest',
            route('seller.viewings.index'),
        );

        $this->notifyListingBroker(
            $property,
            'A buyer requested a viewing for '.$property->title.'.',
            'visit:'.$visit->id.':requested:broker',
        );
    }

    public function siteVisitConfirmed(SiteVisit $visit): void
    {
        $visit->loadMissing(['buyer', 'property', 'assignee']);
        $property = $visit->property;
        $buyer = $visit->buyer;

        if ($property === null || $buyer === null) {
            return;
        }

        $when = $visit->confirmed_for?->timezone(config('app.timezone'))->toDayDateTimeString() ?? 'a time to be confirmed';
        $summary = 'Your site visit for '.$property->title.' is confirmed for '.$when.'.';

        $this->desk(
            $buyer,
            MessageEvent::SiteVisitConfirmed,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'status' => SiteVisitStatus::Confirmed->label(),
                'summary' => $summary,
                'url' => route('buyer.visits.index'),
            ],
            'visit:'.$visit->id.':confirmed:buyer:'.$visit->confirmed_for?->timestamp,
            new SiteVisitUpdatedNotification($visit, $summary),
        );

        $sellerSummary = 'A viewing for '.$property->title.' is confirmed for '.$when.'.';
        $this->notifySellers($property, $sellerSummary, 'visit:'.$visit->id.':confirmed:interest', route('seller.viewings.index'));
        $this->notifyListingBroker($property, $sellerSummary, 'visit:'.$visit->id.':confirmed:broker');

        if ($visit->assignee !== null) {
            $this->desk(
                $visit->assignee,
                MessageEvent::SiteVisitConfirmed,
                [
                    'name' => $visit->assignee->name,
                    'title' => $property->title,
                    'reference' => $property->reference,
                    'summary' => 'Site visit confirmed for '.$property->title.' on '.$when.'.',
                    'url' => route('staff.visits.index'),
                ],
                'visit:'.$visit->id.':confirmed:assignee:'.$visit->assignee->id.':'.$visit->confirmed_for?->timestamp,
                new SiteVisitUpdatedNotification($visit, 'Site visit confirmed.'),
            );
        }
    }

    public function siteVisitRescheduled(SiteVisit $visit, string $summary): void
    {
        $this->siteVisitParticipantNotice($visit, MessageEvent::SiteVisitRescheduled, $summary, 'rescheduled');
    }

    public function siteVisitCancelled(SiteVisit $visit, string $summary): void
    {
        $this->siteVisitParticipantNotice($visit, MessageEvent::SiteVisitCancelled, $summary, 'cancelled');
    }

    public function siteVisitReminder(SiteVisit $visit): void
    {
        $visit->loadMissing(['buyer', 'property', 'assignee']);
        $property = $visit->property;

        if ($property === null || $visit->status !== SiteVisitStatus::Confirmed || $visit->confirmed_for === null) {
            return;
        }

        $when = $visit->confirmed_for->timezone(config('app.timezone'))->toDayDateTimeString();
        $summary = 'Reminder: your viewing for '.$property->title.' is scheduled for '.$when.'.';
        $key = 'visit:'.$visit->id.':reminder:'.$visit->confirmed_for->timestamp;

        $participants = $this->recipients->dedupeUsers(collect([
            $visit->buyer,
            $visit->assignee,
            ...$this->recipients->verifiedSellers($property)->all(),
            $this->recipients->listingBroker($property),
        ]));

        foreach ($participants as $user) {
            $url = $user->is($visit->buyer)
                ? route('buyer.visits.index')
                : ($user->canUseSellerFeatures() ? route('seller.viewings.index') : route('staff.visits.index'));

            $this->desk($user, MessageEvent::SiteVisitReminder, [
                'name' => $user->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => $summary,
                'url' => $url,
            ], $key.':user:'.$user->id);
        }
    }

    public function siteVisitFollowUpRequired(SiteVisit $visit): void
    {
        $visit->loadMissing(['buyer', 'property']);
        $buyer = $visit->buyer;
        $property = $visit->property;

        if ($buyer === null || $property === null) {
            return;
        }

        $this->desk(
            $buyer,
            MessageEvent::SiteVisitFollowUpRequired,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'Your viewing for '.$property->title.' is complete. Open your visits workspace to acknowledge it or take the next step.',
                'url' => route('buyer.visits.index'),
            ],
            'visit:'.$visit->id.':followup',
            new SiteVisitUpdatedNotification($visit, 'Please acknowledge your completed viewing.'),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function staffForEnquiry(Enquiry $enquiry): Collection
    {
        if ($enquiry->assignee !== null && $enquiry->assignee->can('respond', $enquiry)) {
            return collect([$enquiry->assignee]);
        }

        if ($enquiry->purchase_request) {
            $assigned = $enquiry->salesTransaction?->assigned_to;
            $sales = $assigned !== null
                ? User::query()->find($assigned)
                : User::permission('staff.sales')->orderBy('id')->first();

            if ($sales !== null) {
                return collect([$sales]);
            }
        }

        $buyerDesk = User::permission('staff.buyers')->orderBy('id')->first();

        return $buyerDesk !== null ? collect([$buyerDesk]) : collect();
    }

    /**
     * @return Collection<int, User>
     */
    private function staffForVisit(SiteVisit $visit): Collection
    {
        if ($visit->assignee !== null) {
            return collect([$visit->assignee]);
        }

        $buyerDesk = User::permission('staff.buyers')->orderBy('id')->first();

        return $buyerDesk !== null ? collect([$buyerDesk]) : collect();
    }

    private function notifySellers(Property $property, string $summary, string $keyPrefix, string $url): void
    {
        foreach ($this->recipients->verifiedSellers($property) as $seller) {
            $this->desk($seller, MessageEvent::SellerPropertyInterest, [
                'name' => $seller->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => $summary,
                'url' => $url,
            ], $keyPrefix.':seller:'.$seller->id);
        }
    }

    private function notifyListingBroker(Property $property, string $summary, string $keyPrefix): void
    {
        $broker = $this->recipients->listingBroker($property);

        if ($broker === null) {
            return;
        }

        $this->desk($broker, MessageEvent::BrokerListingInterest, [
            'name' => $broker->name,
            'title' => $property->title,
            'reference' => $property->reference,
            'summary' => $summary,
            'url' => $property->submitterUrl(),
        ], $keyPrefix.':broker:'.$broker->id);
    }

    private function siteVisitParticipantNotice(SiteVisit $visit, MessageEvent $event, string $summary, string $transition): void
    {
        $visit->loadMissing(['buyer', 'property', 'assignee']);
        $property = $visit->property;

        if ($property === null) {
            return;
        }

        $stamp = $visit->confirmed_for?->timestamp ?? now()->timestamp;
        $key = 'visit:'.$visit->id.':'.$transition.':'.$stamp;

        $participants = $this->recipients->dedupeUsers(collect([
            $visit->buyer,
            $visit->assignee,
            ...$this->recipients->verifiedSellers($property)->all(),
            $this->recipients->listingBroker($property),
        ]));

        foreach ($participants as $user) {
            $url = $user->is($visit->buyer)
                ? route('buyer.visits.index')
                : ($user->canUseSellerFeatures() ? route('seller.viewings.index') : route('staff.visits.index'));

            $inApp = $user->is($visit->buyer)
                ? new SiteVisitUpdatedNotification($visit, $summary)
                : null;

            $this->desk($user, $event, [
                'name' => $user->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => $summary,
                'url' => $url,
            ], $key.':user:'.$user->id, $inApp);
        }
    }

    /**
     * @param  array<string, string>  $context
     */
    private function desk(User $user, MessageEvent $event, array $context, string $subjectKey, ?Notification $inApp = null): void
    {
        app(MessageDesk::class)->send($user, $event, $context, $subjectKey, $inApp);
    }
}
