<?php

namespace App\Services\Buyer;

use App\Enums\DocumentAccessStatus;
use App\Enums\MessageEvent;
use App\Enums\OfferStatus;
use App\Enums\PropertyMatchStatus;
use App\Enums\SiteVisitStatus;
use App\Models\BrokerIntroduction;
use App\Models\BuyerTimelineEntry;
use App\Models\DocumentAccessRequest;
use App\Models\Enquiry;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\PropertyVisitRequest;
use App\Models\SiteVisit;
use App\Models\SourcingMatch;
use App\Models\User;
use App\Notifications\DocumentAccessDecidedNotification;
use App\Notifications\MatchApprovedNotification;
use App\Notifications\OfferStatusNotification;
use App\Notifications\OfferSubmittedNotification;
use App\Notifications\SiteVisitUpdatedNotification;
use App\Notifications\ViewingAcknowledgedNotification;
use App\Services\Communications\MessageDesk;
use App\Services\Sales\SaleModeTerms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

class InterestWorkflow
{
    public function approve(SourcingMatch $match, User $staff): PropertyMatch
    {
        $match->loadMissing('sourcingRequest', 'property');
        $buyer = $match->sourcingRequest->buyer;
        $enquiry = Enquiry::query()
            ->where('buyer_id', $buyer->id)
            ->where('property_id', $match->property_id)
            ->latest()
            ->first();

        $propertyMatch = PropertyMatch::query()->updateOrCreate(
            [
                'buyer_id' => $buyer->id,
                'property_id' => $match->property_id,
            ],
            [
                'sourcing_request_id' => $match->sourcing_request_id,
                'enquiry_id' => $enquiry?->id,
                'approved_by' => $staff->id,
                'status' => PropertyMatchStatus::Shortlisted,
                'buyer_feedback' => null,
                'approved_at' => now(),
            ],
        );

        $summary = $match->property->title.' added to the shortlist.';
        $this->timeline($buyer, $summary, $propertyMatch, $staff);
        app(MessageDesk::class)->send($buyer, MessageEvent::SourcingMatch, [
            'name' => $buyer->name,
            'title' => $match->property->title,
            'summary' => $match->property->title.' matches your search.',
            'url' => route('buyer.matches.show', $propertyMatch),
        ], 'match:'.$propertyMatch->id, new MatchApprovedNotification($propertyMatch));

        $match->forceFill(['dismissed_at' => null, 'dismissed_by' => null])->save();

        return $propertyMatch;
    }

    public function declineCandidate(SourcingMatch $match, User $staff): void
    {
        $match->loadMissing('sourcingRequest', 'property');

        if ($match->dismissed_at !== null) {
            return;
        }

        $match->forceFill([
            'dismissed_at' => now(),
            'dismissed_by' => $staff->id,
        ])->save();

        $match->sourcingRequest->activities()->create([
            'actor_id' => $staff->id,
            'summary' => $match->property->title.' was not added to the shortlist.',
            'visible_to_buyer' => false,
        ]);
    }

    public function reject(PropertyMatch $match, User $buyer, string $feedback): PropertyMatch
    {
        $match->status = PropertyMatchStatus::Rejected;
        $match->buyer_feedback = trim($feedback);
        $match->save();
        $this->timeline($buyer, $match->property->title.' removed from the shortlist.', $match, $buyer);

        return $match;
    }

    public function requestVisit(PropertyMatch $match, User $buyer, Carbon $requestedFor, ?string $notes): SiteVisit
    {
        $visit = $match->visits()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $match->property_id,
            'enquiry_id' => $match->enquiry_id,
            'status' => SiteVisitStatus::Requested,
            'requested_for' => $requestedFor,
            'notes' => $notes,
        ]);

        $this->timeline($buyer, 'Site visit requested for '.$match->property->title.'.', $match, $buyer);
        $this->notifyDesk(new SiteVisitUpdatedNotification($visit, 'A buyer requested a site visit.'));

        return $visit;
    }

    public function requestListingVisit(PropertyVisitRequest $request): SiteVisit
    {
        $requestedFor = $request->preferred_on->copy()->startOfDay();
        $visit = SiteVisit::query()
            ->where('buyer_id', $request->buyer_id)
            ->where('property_id', $request->property_id)
            ->whereDate('requested_for', $requestedFor->toDateString())
            ->whereIn('status', [SiteVisitStatus::Requested, SiteVisitStatus::Confirmed])
            ->first();

        if ($visit !== null) {
            return $visit;
        }

        $visit = SiteVisit::query()->create([
            'buyer_id' => $request->buyer_id,
            'property_id' => $request->property_id,
            'property_visit_request_id' => $request->id,
            'status' => SiteVisitStatus::Requested,
            'requested_for' => $requestedFor,
            'notes' => $request->note,
        ]);

        BuyerTimelineEntry::record($request->buyer, 'Site visit requested for '.$request->property->title.'.', [
            'property_id' => $request->property_id,
        ], $request->buyer);

        try {
            $this->notifyDesk(new SiteVisitUpdatedNotification($visit, 'A buyer requested a site visit.'));
        } catch (Throwable) {
            // The visit remains in the authorized staff queue if notification delivery fails.
        }

        return $visit;
    }

    public function assignVisit(SiteVisit $visit, User $agent, User $actor): SiteVisit
    {
        if ((int) $visit->assigned_to === (int) $agent->id) {
            return $visit;
        }
        $visit->assigned_to = $agent->id;
        $visit->save();
        $visit->match?->forceFill(['assigned_to' => $agent->id])->save();
        $this->visitTimeline($visit, 'A site visit agent was assigned.', $actor);
        $agent->notify(new SiteVisitUpdatedNotification($visit, 'A site visit was assigned to you.'));

        return $visit;
    }

    public function transitionVisit(SiteVisit $visit, SiteVisitStatus $status, User $actor, ?Carbon $confirmedFor = null): SiteVisit
    {
        if (! in_array($status, $visit->status->transitions(), true)) {
            throw ValidationException::withMessages([
                'status' => 'This visit cannot move to that status.',
            ]);
        }

        $visit->status = $status;

        if ($status === SiteVisitStatus::Confirmed) {
            $visit->confirmed_for = $confirmedFor ?? $visit->requested_for;
            $visit->buyer_confirmed_at = null;
        }

        if ($status === SiteVisitStatus::Cancelled) {
            $visit->cancelled_at = now();
        }

        if ($status === SiteVisitStatus::Completed) {
            $visit->completed_at = now();
        }

        $visit->save();
        $this->visitTimeline($visit, 'Site visit '.$status->label().' for '.$visit->property->title.'.', $actor);

        if ($actor->is($visit->buyer)) {
            $this->notifyDesk(new SiteVisitUpdatedNotification($visit, 'A buyer updated a site visit.'));
        } elseif ($status !== SiteVisitStatus::Confirmed) {
            $visit->buyer->notify(new SiteVisitUpdatedNotification($visit, 'Your site visit is now '.$status->label().'.'));
        }

        if ($status === SiteVisitStatus::Confirmed) {
            $this->notifyVisitConfirmed($visit);
        }

        return $visit;
    }

    public function confirmVisitDetails(SiteVisit $visit, User $buyer): SiteVisit
    {
        if ($visit->status !== SiteVisitStatus::Confirmed || $visit->buyer_confirmed_at !== null) {
            throw ValidationException::withMessages(['visit' => 'This confirmed appointment can no longer be acknowledged.']);
        }

        $visit->buyer_confirmed_at = now();
        $visit->save();
        $this->visitTimeline($visit, 'Buyer confirmed the appointment details.', $buyer);
        $this->notifyVisitTeam($visit, new SiteVisitUpdatedNotification($visit, 'The buyer confirmed the appointment details.'));

        return $visit;
    }

    public function requestVisitReschedule(SiteVisit $visit, User $buyer, Carbon $preferredFor, ?string $note): SiteVisit
    {
        if ($visit->status !== SiteVisitStatus::Confirmed || $visit->reschedule_requested_at !== null) {
            throw ValidationException::withMessages(['visit' => 'A reschedule request is not available for this visit.']);
        }

        $visit->forceFill([
            'reschedule_requested_at' => now(),
            'reschedule_preferred_for' => $preferredFor,
            'reschedule_note' => filled($note) ? trim((string) $note) : null,
        ])->save();
        $this->visitTimeline($visit, 'Buyer requested a change to the confirmed visit time.', $buyer);
        $this->notifyVisitTeam($visit, new SiteVisitUpdatedNotification($visit, 'The buyer requested a reschedule.'));

        return $visit;
    }

    public function resolveVisitReschedule(
        SiteVisit $visit,
        User $actor,
        bool $accept,
        ?Carbon $confirmedFor,
        string $response,
    ): SiteVisit {
        if ($visit->status !== SiteVisitStatus::Confirmed || $visit->reschedule_requested_at === null) {
            throw ValidationException::withMessages(['visit' => 'There is no pending reschedule request for this visit.']);
        }

        if ($accept && $confirmedFor === null) {
            throw ValidationException::withMessages(['confirmed_for' => 'Choose the confirmed date and time.']);
        }

        if ($accept) {
            $visit->confirmed_for = $confirmedFor;
            $visit->buyer_confirmed_at = null;
        }

        $decision = $accept ? 'accepted' : 'declined';
        $visit->forceFill([
            'reschedule_requested_at' => null,
            'reschedule_preferred_for' => null,
            'reschedule_note' => null,
        ])->save();
        $summary = 'Your reschedule request was '.$decision.'. '.trim($response);
        $this->visitTimeline($visit, 'Reschedule request '.$decision.'. '.trim($response), $actor);

        if ($accept) {
            $this->notifyVisitConfirmed($visit);
        } else {
            $visit->buyer->notify(new SiteVisitUpdatedNotification($visit, $summary));
        }

        return $visit;
    }

    public function acknowledge(SiteVisit $visit, User $buyer): SiteVisit
    {
        if ($visit->status !== SiteVisitStatus::Completed || $visit->buyer_acknowledged_at !== null) {
            throw ValidationException::withMessages([
                'visit' => 'A completed visit can be acknowledged once.',
            ]);
        }

        $visit->buyer_acknowledged_at = now();
        $visit->save();
        $this->timeline($buyer, 'Viewing acknowledged for '.$visit->property->title.'.', $visit->match, $buyer);

        $recipient = $visit->assignee;
        if ($recipient) {
            $recipient->notify(new ViewingAcknowledgedNotification($visit));
        } else {
            $this->notifyDesk(new ViewingAcknowledgedNotification($visit));
        }

        return $visit;
    }

    public function submitOffer(PropertyMatch $match, User $buyer, int $amount, ?float $requestedAcreage, ?string $message): Offer
    {
        $match->loadMissing('property');
        $this->guardOpenOffer($buyer->id, $match->property_id);
        $acreage = $this->normalizeOfferAcreage($match->property, $requestedAcreage);

        $offer = $match->offers()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $match->property_id,
            'enquiry_id' => $match->enquiry_id,
            'amount' => $amount,
            'requested_acreage' => $acreage,
            'currency' => 'KES',
            'message' => $message,
            'status' => OfferStatus::Submitted,
        ]);

        $this->timeline($buyer, 'Offer submitted for '.$match->property->title.'.', $match, $buyer);
        $this->notifyOfferSubmitted($offer);

        return $offer;
    }

    public function submitListingOffer(User $buyer, Property $property, int $amount, ?float $requestedAcreage, ?string $message): Offer
    {
        if (! $property->isPubliclyListed()) {
            throw ValidationException::withMessages([
                'listing' => 'Offers can only be made on published listings.',
            ]);
        }

        $this->guardOpenOffer($buyer->id, $property->id);
        $acreage = $this->normalizeOfferAcreage($property, $requestedAcreage);

        $match = PropertyMatch::query()->firstOrCreate(
            [
                'buyer_id' => $buyer->id,
                'property_id' => $property->id,
            ],
            [
                'status' => PropertyMatchStatus::Shortlisted,
                'approved_at' => now(),
            ],
        );

        $offer = Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'property_match_id' => $match->id,
            'enquiry_id' => $match->enquiry_id,
            'amount' => $amount,
            'requested_acreage' => $acreage,
            'currency' => 'KES',
            'message' => $message,
            'status' => OfferStatus::Submitted,
        ]);

        $this->timeline($buyer, 'Offer submitted on '.$property->title.'.', $match, $buyer);
        $this->notifyOfferSubmitted($offer);

        return $offer;
    }

    public function submitBrokerOffer(
        User $broker,
        BrokerIntroduction $introduction,
        Property $property,
        int $amount,
        ?float $requestedAcreage,
        ?string $message,
    ): Offer {
        if (! $broker->canUseBrokerFeatures()) {
            throw ValidationException::withMessages([
                'broker' => 'Broker capability is required to submit an offer for a buyer.',
            ]);
        }

        if ((int) $introduction->broker_id !== (int) $broker->id) {
            throw ValidationException::withMessages([
                'introduction' => 'This introduction does not belong to your brokerage account.',
            ]);
        }

        if ($introduction->buyer_id === null) {
            throw ValidationException::withMessages([
                'introduction' => 'The buyer must be linked to a registered account before you can submit an offer.',
            ]);
        }

        if (! BrokerIntroduction::query()
            ->whereKey($introduction->id)
            ->activeForBuyer($introduction->buyer, $property)
            ->exists()) {
            throw ValidationException::withMessages([
                'introduction' => 'This introduction is not authorized for this property.',
            ]);
        }

        if (! $property->isPubliclyListed()) {
            throw ValidationException::withMessages([
                'listing' => 'Offers can only be made on published listings.',
            ]);
        }

        $buyer = $introduction->buyer;
        $this->guardOpenOffer($buyer->id, $property->id);
        $acreage = $this->normalizeOfferAcreage($property, $requestedAcreage);

        $match = PropertyMatch::query()->firstOrCreate(
            [
                'buyer_id' => $buyer->id,
                'property_id' => $property->id,
            ],
            [
                'status' => PropertyMatchStatus::Shortlisted,
                'approved_at' => now(),
            ],
        );

        $offer = Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'property_match_id' => $match->id,
            'broker_introduction_id' => $introduction->id,
            'submitted_by_broker_id' => $broker->id,
            'amount' => $amount,
            'requested_acreage' => $acreage,
            'currency' => 'KES',
            'message' => $message,
            'status' => OfferStatus::Submitted,
        ]);

        $this->timeline($buyer, 'Offer submitted by broker on '.$property->title.'.', $match, $broker);
        $this->notifyOfferSubmitted($offer);

        return $offer;
    }

    private function guardOpenOffer(int $buyerId, int $propertyId): void
    {
        $open = Offer::query()
            ->where('buyer_id', $buyerId)
            ->where('property_id', $propertyId)
            ->whereIn('status', [OfferStatus::Submitted, OfferStatus::UnderReview])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'amount' => 'An open offer already exists for this buyer on this property.',
            ]);
        }
    }

    private function normalizeOfferAcreage(Property $property, ?float $requestedAcreage): float
    {
        app(SaleModeTerms::class)->validatePurchaseAcreage($property, $requestedAcreage);
        $available = round($property->offeredAcreage(), 4);

        if ($requestedAcreage !== null) {
            return round($requestedAcreage, 4);
        }

        return $available;
    }

    private function notifyOfferSubmitted(Offer $offer): void
    {
        $recipients = collect();

        foreach (['staff.sales', 'staff.buyers', 'staff.properties'] as $permission) {
            $recipients = $recipients->merge(User::permission($permission)->get());
        }

        Notification::send($recipients->unique('id'), new OfferSubmittedNotification($offer));
    }

    public function reflectSellerPresentation(Offer $offer, OfferStatus $status, User $seller): Offer
    {
        if (! in_array($status, [OfferStatus::Accepted, OfferStatus::Declined], true)) {
            throw ValidationException::withMessages(['status' => 'Seller responses can only accept or decline.']);
        }

        if (! in_array($status, $offer->status->transitions(), true)) {
            return $offer;
        }

        $offer->status = $status;
        $offer->save();

        $summary = 'The seller '.$status->label().' your offer for '.$offer->property->title.'.';
        $this->timeline($offer->buyer, $summary, $offer->match, $seller);

        app(MessageDesk::class)->send($offer->buyer, MessageEvent::OfferStatus, [
            'name' => $offer->buyer->name,
            'title' => $offer->property->title,
            'status' => $status->label(),
            'summary' => $summary,
            'url' => route('buyer.offers.index'),
        ], 'offer:'.$offer->id.':seller:'.$status->value, new OfferStatusNotification($offer));

        $this->notifyDesk(new OfferStatusNotification($offer));

        return $offer;
    }

    public function transitionOffer(Offer $offer, OfferStatus $status, User $actor): Offer
    {
        if (! in_array($status, $offer->status->transitions(), true)) {
            throw ValidationException::withMessages([
                'status' => 'This offer cannot move to that status.',
            ]);
        }

        $offer->status = $status;
        $offer->save();

        $this->timeline($offer->buyer, 'Offer '.$status->label().' for '.$offer->property->title.'.', $offer->match, $actor);

        if ($actor->is($offer->buyer)) {
            $this->notifyDesk(new OfferStatusNotification($offer));
        } else {
            app(MessageDesk::class)->send($offer->buyer, MessageEvent::OfferStatus, [
                'name' => $offer->buyer->name,
                'title' => $offer->property->title,
                'status' => $status->label(),
                'summary' => 'Your offer for '.$offer->property->title.' is now '.$status->label().'.',
                'url' => route('buyer.offers.index'),
            ], 'offer:'.$offer->id.':'.$status->value, new OfferStatusNotification($offer));
        }

        return $offer;
    }

    public function requestDocuments(PropertyMatch $match, User $buyer, ?string $note): DocumentAccessRequest
    {
        $existing = $match->documentRequests()->where('status', DocumentAccessStatus::Requested)->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'note' => 'A document request is already waiting for review.',
            ]);
        }

        $request = $match->documentRequests()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $match->property_id,
            'status' => DocumentAccessStatus::Requested,
            'note' => $note,
        ]);

        $this->timeline($buyer, 'Document access requested for '.$match->property->title.'.', $match, $buyer);
        $this->notifyDesk(new DocumentAccessDecidedNotification($request, 'A buyer requested document access.'));

        return $request;
    }

    public function decideDocuments(DocumentAccessRequest $request, DocumentAccessStatus $status, User $staff): DocumentAccessRequest
    {
        if ($request->status !== DocumentAccessStatus::Requested || $status === DocumentAccessStatus::Requested) {
            throw ValidationException::withMessages([
                'status' => 'This document request has already been decided.',
            ]);
        }

        $request->status = $status;
        $request->approved_by = $staff->id;
        $request->save();
        $this->timeline($request->buyer, 'Document access '.$status->label().' for '.$request->property->title.'.', $request->match, $staff);
        $request->buyer->notify(new DocumentAccessDecidedNotification($request, 'Document access is '.$status->label().'.'));

        return $request;
    }

    private function timeline(User $buyer, string $summary, ?PropertyMatch $match, User $actor): void
    {
        BuyerTimelineEntry::record($buyer, $summary, [
            'property_id' => $match?->property_id,
            'sourcing_request_id' => $match?->sourcing_request_id,
            'property_match_id' => $match?->id,
        ], $actor);
    }

    private function visitTimeline(SiteVisit $visit, string $summary, User $actor): void
    {
        BuyerTimelineEntry::record($visit->buyer, $summary, [
            'property_id' => $visit->property_id,
            'sourcing_request_id' => $visit->match?->sourcing_request_id,
            'property_match_id' => $visit->property_match_id,
        ], $actor);
    }

    private function notifyDesk(object $notification): void
    {
        Notification::send(User::permission('staff.buyers')->get(), $notification);
    }

    private function notifyVisitTeam(SiteVisit $visit, object $notification): void
    {
        $recipients = User::permission('staff.buyers')->get();
        if ($visit->assignee !== null) {
            $recipients->push($visit->assignee);
        }

        Notification::send($recipients->unique('id'), $notification);
    }

    private function notifyVisitConfirmed(SiteVisit $visit): void
    {
        $confirmedFor = $visit->confirmed_for?->timezone(config('app.timezone'))->toDayDateTimeString() ?? 'a time to be confirmed';
        $summary = 'Your site visit for '.$visit->property->title.' is confirmed for '.$confirmedFor.'.';

        app(MessageDesk::class)->send($visit->buyer, MessageEvent::SiteVisitConfirmed, [
            'name' => $visit->buyer->name,
            'title' => $visit->property->title,
            'status' => SiteVisitStatus::Confirmed->label(),
            'summary' => $summary,
            'url' => route('buyer.visits.index'),
        ], 'visit:'.$visit->id.':'.$visit->confirmed_for?->timestamp, new SiteVisitUpdatedNotification($visit, $summary));
    }
}
