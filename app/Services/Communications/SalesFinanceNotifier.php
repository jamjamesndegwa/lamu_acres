<?php

namespace App\Services\Communications;

use App\Enums\AgencyFeePayer;
use App\Enums\ClosingEvidenceState;
use App\Enums\DocumentAccessStatus;
use App\Enums\MessageEvent;
use App\Enums\OfferStatus;
use App\Enums\SalesOutcome;
use App\Models\AgencyFeeAgreement;
use App\Models\AgencyFeeReceipt;
use App\Models\CommissionAgreement;
use App\Models\DocumentAccessRequest;
use App\Models\Offer;
use App\Models\OfferPresentation;
use App\Models\SalesTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\DocumentAccessDecidedNotification;
use App\Notifications\OfferPresentedNotification;
use App\Notifications\OfferStatusNotification;
use App\Notifications\OfferSubmittedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class SalesFinanceNotifier
{
    public function __construct(private PropertyInterestRecipients $recipients) {}

    public function offerSubmitted(Offer $offer): void
    {
        $offer->loadMissing(['buyer', 'property', 'brokerIntroduction.broker', 'submittedByBroker']);

        if ($offer->buyer === null || $offer->property === null) {
            return;
        }

        $this->desk(
            $offer->buyer,
            MessageEvent::OfferSubmittedAcknowledged,
            [
                'name' => $offer->buyer->name,
                'title' => $offer->property->title,
                'reference' => $offer->property->reference,
                'summary' => 'We received your offer on '.$offer->property->title.'. Staff will review it; this is not a completed sale.',
                'url' => route('buyer.offers.index'),
            ],
            'offer:'.$offer->id.':submitted:buyer',
        );

        foreach ($this->offerDeskStaff() as $staff) {
            $this->desk(
                $staff,
                MessageEvent::OfferSubmittedStaffAlert,
                [
                    'name' => $staff->name,
                    'title' => $offer->property->title,
                    'reference' => $offer->property->reference,
                    'summary' => 'An offer was submitted on '.$offer->property->title.'.',
                    'url' => route('staff.offers.show', $offer),
                ],
                'offer:'.$offer->id.':submitted:staff:'.$staff->id,
                new OfferSubmittedNotification($offer),
            );
        }

        $broker = $this->offerBroker($offer);

        if ($broker !== null && ! $broker->is($offer->buyer)) {
            $this->desk(
                $broker,
                MessageEvent::OfferSubmittedBrokerNotice,
                [
                    'name' => $broker->name,
                    'title' => $offer->property->title,
                    'reference' => $offer->property->reference,
                    'summary' => 'An offer was submitted for your introduced buyer on '.$offer->property->title.'.',
                    'url' => route('broker.dashboard'),
                ],
                'offer:'.$offer->id.':submitted:broker:'.$broker->id,
            );
        }
    }

    public function offerPresented(OfferPresentation $presentation): void
    {
        $presentation->loadMissing('offer.property', 'offer.buyer');
        $offer = $presentation->offer;
        $property = $offer?->property;

        if ($property === null) {
            return;
        }

        foreach ($this->recipients->verifiedSellers($property) as $seller) {
            $this->desk(
                $seller,
                MessageEvent::OfferPresentedToSeller,
                [
                    'name' => $seller->name,
                    'title' => $property->title,
                    'reference' => $property->reference,
                    'summary' => 'Staff presented a formal offer on '.$property->title.' for your review. Open your seller workspace to respond.',
                    'url' => route('seller.offers.index'),
                ],
                'offer:'.$offer->id.':presented:seller:'.$seller->id.':presentation:'.$presentation->id,
                new OfferPresentedNotification($presentation),
            );
        }
    }

    public function offerSellerResponse(Offer $offer, OfferStatus $status): void
    {
        $offer->loadMissing(['buyer', 'property', 'brokerIntroduction.broker', 'submittedByBroker']);

        if ($offer->buyer === null || $offer->property === null) {
            return;
        }

        $summary = 'The seller '.$status->label().' your offer for '.$offer->property->title.'. This does not complete the land sale.';

        $this->desk(
            $offer->buyer,
            MessageEvent::OfferStatus,
            [
                'name' => $offer->buyer->name,
                'title' => $offer->property->title,
                'reference' => $offer->property->reference,
                'status' => $status->label(),
                'summary' => $summary,
                'url' => route('buyer.offers.index'),
            ],
            'offer:'.$offer->id.':seller:'.$status->value,
            new OfferStatusNotification($offer),
        );

        $this->notifyOfferStaff($offer, 'Offer '.$status->label().' on '.$offer->property->title.'.', 'offer:'.$offer->id.':seller:staff:'.$status->value);

        $broker = $this->offerBroker($offer);

        if ($broker !== null && ! $broker->is($offer->buyer)) {
            $this->desk(
                $broker,
                MessageEvent::OfferStatus,
                [
                    'name' => $broker->name,
                    'title' => $offer->property->title,
                    'reference' => $offer->property->reference,
                    'status' => $status->label(),
                    'summary' => $summary,
                    'url' => route('broker.dashboard'),
                ],
                'offer:'.$offer->id.':seller:broker:'.$status->value,
            );
        }
    }

    public function offerTransition(Offer $offer, OfferStatus $status, User $actor): void
    {
        $offer->loadMissing(['buyer', 'property', 'presentations', 'brokerIntroduction.broker', 'submittedByBroker']);

        if ($offer->buyer === null || $offer->property === null) {
            return;
        }

        if ($status === OfferStatus::Withdrawn) {
            $this->notifyOfferStaff($offer, 'An offer on '.$offer->property->title.' was withdrawn.', 'offer:'.$offer->id.':withdrawn:staff');

            if ($offer->presentations()->exists()) {
                foreach ($this->recipients->verifiedSellers($offer->property) as $seller) {
                    $this->desk(
                        $seller,
                        MessageEvent::OfferWithdrawnAlert,
                        [
                            'name' => $seller->name,
                            'title' => $offer->property->title,
                            'reference' => $offer->property->reference,
                            'summary' => 'A presented offer on '.$offer->property->title.' was withdrawn.',
                            'url' => route('seller.offers.index'),
                        ],
                        'offer:'.$offer->id.':withdrawn:seller:'.$seller->id,
                    );
                }
            }

            return;
        }

        if ($actor->is($offer->buyer)) {
            $this->notifyOfferStaff($offer, 'Offer '.$status->label().' on '.$offer->property->title.'.', 'offer:'.$offer->id.':buyer-action:staff:'.$status->value);

            return;
        }

        $summary = 'Your offer for '.$offer->property->title.' is now '.$status->label().'.';

        $this->desk(
            $offer->buyer,
            MessageEvent::OfferStatus,
            [
                'name' => $offer->buyer->name,
                'title' => $offer->property->title,
                'reference' => $offer->property->reference,
                'status' => $status->label(),
                'summary' => $summary,
                'url' => route('buyer.offers.index'),
            ],
            'offer:'.$offer->id.':'.$status->value,
            new OfferStatusNotification($offer),
        );

        $broker = $this->offerBroker($offer);

        if ($broker !== null && ! $broker->is($offer->buyer) && $status === OfferStatus::UnderReview) {
            $this->desk(
                $broker,
                MessageEvent::OfferStatus,
                [
                    'name' => $broker->name,
                    'title' => $offer->property->title,
                    'reference' => $offer->property->reference,
                    'status' => $status->label(),
                    'summary' => 'An offer for your buyer on '.$offer->property->title.' needs a response.',
                    'url' => route('broker.dashboard'),
                ],
                'offer:'.$offer->id.':negotiation:broker:'.$status->value,
            );
        }
    }

    public function saleTermsAgreed(SalesTransaction $transaction, string $summary, string $keySuffix): void
    {
        $transaction->loadMissing('buyer', 'property');
        $buyer = $transaction->buyer;

        if ($buyer === null) {
            return;
        }

        $url = $transaction->enquiry_id
            ? route('buyer.enquiries.show', $transaction->enquiry_id)
            : route('buyer.matches.index');

        $this->desk($buyer, MessageEvent::SaleTermsAgreed, [
            'name' => $buyer->name,
            'title' => $transaction->property->title,
            'reference' => $transaction->reference,
            'summary' => $summary,
            'url' => $url,
        ], 'sale:'.$transaction->id.':terms:'.$keySuffix);
    }

    public function saleOutcome(SalesTransaction $transaction, SalesOutcome $outcome): void
    {
        $transaction->loadMissing('buyer', 'property');
        $buyer = $transaction->buyer;

        if ($buyer === null || $outcome === SalesOutcome::Disputed) {
            return;
        }

        $url = $transaction->enquiry_id
            ? route('buyer.enquiries.show', $transaction->enquiry_id)
            : route('buyer.matches.index');

        $this->desk($buyer, MessageEvent::SaleOutcomeUpdated, [
            'name' => $buyer->name,
            'title' => $transaction->property->title,
            'reference' => $transaction->reference,
            'summary' => 'Your purchase case for '.$transaction->property->title.' is '.$outcome->label().'.',
            'url' => $url,
        ], 'sale:'.$transaction->id.':outcome:'.$outcome->value);
    }

    public function closingReview(SalesTransaction $transaction, string $decision): void
    {
        $transaction->loadMissing('buyer', 'property');
        $buyer = $transaction->buyer;

        if ($buyer === null) {
            return;
        }

        $summary = $decision === 'authorized'
            ? 'Closing review for '.$transaction->property->title.' was authorized. Completion still requires accepted evidence.'
            : 'Closing review for '.$transaction->property->title.' needs correction before completion.';

        $this->desk($buyer, MessageEvent::ClosingReviewUpdate, [
            'name' => $buyer->name,
            'title' => $transaction->property->title,
            'reference' => $transaction->reference,
            'summary' => $summary,
            'url' => route('staff.sales.show', $transaction),
        ], 'sale:'.$transaction->id.':closing-review:'.$decision);
    }

    public function closingEvidenceUpdated(SalesTransaction $transaction, string $label, ClosingEvidenceState $state): void
    {
        if ($state === ClosingEvidenceState::Accepted) {
            return;
        }

        $transaction->loadMissing('buyer', 'property');
        $buyer = $transaction->buyer;

        if ($buyer === null) {
            return;
        }

        $this->desk($buyer, MessageEvent::ClosingEvidenceUpdate, [
            'name' => $buyer->name,
            'title' => $transaction->property->title,
            'reference' => $transaction->reference,
            'summary' => 'Staff updated closing evidence ('.$label.') for '.$transaction->property->title.'. Open your case for details.',
            'url' => $transaction->enquiry_id
                ? route('buyer.enquiries.show', $transaction->enquiry_id)
                : route('buyer.matches.index'),
        ], 'sale:'.$transaction->id.':evidence:'.$label.':'.$state->value);
    }

    public function documentAccessDecided(DocumentAccessRequest $request): void
    {
        $request->loadMissing(['buyer', 'property', 'match']);
        $buyer = $request->buyer;
        $property = $request->property;

        if ($buyer === null || $property === null) {
            return;
        }

        $matchUrl = $request->match !== null
            ? route('buyer.matches.show', $request->match)
            : route('buyer.sourcing.index');

        $label = $request->status === DocumentAccessStatus::Approved ? 'approved' : 'denied';

        $this->desk(
            $buyer,
            MessageEvent::DocumentAccessDecided,
            [
                'name' => $buyer->name,
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'Document access for '.$property->title.' was '.$label.'. Open your workspace for authorized next steps. Documents are not attached to this email.',
                'url' => $matchUrl,
            ],
            'document:'.$request->id.':decided:'.$label,
            new DocumentAccessDecidedNotification($request, 'Document access is '.$request->status->label().'.'),
        );
    }

    public function commissionEarned(CommissionAgreement $agreement): void
    {
        $agreement->loadMissing('broker', 'transaction.property');

        if ($agreement->broker === null) {
            return;
        }

        $this->desk(
            $agreement->broker,
            MessageEvent::CommissionEarned,
            [
                'name' => $agreement->broker->name,
                'title' => $agreement->transaction->property->title,
                'reference' => $agreement->reference,
                'summary' => 'Commission was earned on '.$agreement->transaction->property->title.'. Finance will confirm when it is payable.',
                'url' => route('broker.agreements.show', $agreement),
            ],
            'commission:'.$agreement->id.':earned',
        );
    }

    public function agencyFeePayable(AgencyFeeAgreement $agreement): void
    {
        $agreement->loadMissing('transaction.property', 'seller');

        $payer = $this->agencyFeePayer($agreement);
        $summary = 'An agency fee is payable for '.$agreement->transaction->property->title.'.';

        if ($payer !== null) {
            $this->desk($payer, MessageEvent::AgencyFeePayable, [
                'name' => $payer->name,
                'title' => $agreement->transaction->property->title,
                'reference' => $agreement->reference,
                'summary' => $summary,
                'url' => route('seller.transactions.index'),
            ], 'agency:'.$agreement->id.':payable:payer:'.$payer->id);
        }

        foreach ($this->financeStaff() as $staff) {
            $this->desk($staff, MessageEvent::AgencyFeePayable, [
                'name' => $staff->name,
                'title' => $agreement->transaction->property->title,
                'reference' => $agreement->reference,
                'summary' => $summary,
                'url' => route('staff.agency-fees.show', $agreement),
            ], 'agency:'.$agreement->id.':payable:finance:'.$staff->id);
        }
    }

    public function agencyFeeReceiptConfirmed(AgencyFeeReceipt $receipt): void
    {
        $receipt->loadMissing('agreement.transaction.property', 'agreement.seller');
        $agreement = $receipt->agreement;

        if ($agreement === null) {
            return;
        }

        $summary = 'An agency fee receipt was confirmed for '.$agreement->transaction->property->title.'.';

        $payer = $this->agencyFeePayer($agreement);

        if ($payer !== null) {
            $this->desk($payer, MessageEvent::AgencyFeeReceiptConfirmed, [
                'name' => $payer->name,
                'title' => $agreement->transaction->property->title,
                'reference' => $agreement->reference,
                'summary' => $summary,
                'url' => route('seller.transactions.index'),
            ], 'agency:'.$agreement->id.':receipt:'.$receipt->id.':payer');
        }

        foreach ($this->financeStaff() as $staff) {
            $this->desk($staff, MessageEvent::AgencyFeeReceiptConfirmed, [
                'name' => $staff->name,
                'title' => $agreement->transaction->property->title,
                'reference' => $agreement->reference,
                'summary' => $summary,
                'url' => route('staff.agency-fees.show', $agreement),
            ], 'agency:'.$agreement->id.':receipt:'.$receipt->id.':finance:'.$staff->id);
        }
    }

    public function subscriptionActivated(Subscription $subscription): void
    {
        $member = $subscription->user;

        if ($member === null) {
            return;
        }

        $subscription->loadMissing('plan');
        $stamp = $subscription->starts_at?->timestamp ?? now()->timestamp;

        $this->desk($member, MessageEvent::SubscriptionActivated, [
            'name' => $member->name,
            'title' => $subscription->plan->name ?? 'Investor Pass',
            'reference' => (string) $subscription->id,
            'summary' => 'Your Investor Pass is active. Premium research is available in your workspace.',
            'url' => route('buyer.subscription.show'),
        ], 'subscription:'.$subscription->id.':activated:'.$stamp);
    }

    public function subscriptionExpiringSoon(Subscription $subscription): void
    {
        $member = $subscription->user;

        if ($member === null || $subscription->ends_at === null) {
            return;
        }

        $subscription->loadMissing('plan');

        $this->desk($member, MessageEvent::SubscriptionExpiringSoon, [
            'name' => $member->name,
            'title' => $subscription->plan->name ?? 'Investor Pass',
            'reference' => (string) $subscription->id,
            'summary' => 'Your Investor Pass expires on '.$subscription->ends_at->timezone(config('app.timezone'))->toFormattedDateString().'. Renew in your workspace to keep access.',
            'url' => route('buyer.subscription.show'),
        ], 'subscription:'.$subscription->id.':expiring:'.$subscription->ends_at->timestamp);
    }

    private function offerBroker(Offer $offer): ?User
    {
        if ($offer->submittedByBroker !== null) {
            return $offer->submittedByBroker;
        }

        return $offer->brokerIntroduction?->broker;
    }

    private function notifyOfferStaff(Offer $offer, string $summary, string $key): void
    {
        foreach ($this->offerDeskStaff() as $staff) {
            $this->desk($staff, MessageEvent::OfferSubmittedStaffAlert, [
                'name' => $staff->name,
                'title' => $offer->property->title,
                'reference' => $offer->property->reference,
                'summary' => $summary,
                'url' => route('staff.offers.show', $offer),
            ], $key.':'.$staff->id, new OfferStatusNotification($offer));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function offerDeskStaff(): Collection
    {
        return User::permission('staff.sales')
            ->get()
            ->merge(User::permission('staff.buyers')->get())
            ->merge(User::permission('staff.properties')->get())
            ->unique('id')
            ->sortBy('id')
            ->values();
    }

    private function agencyFeePayer(AgencyFeeAgreement $agreement): ?User
    {
        if ($agreement->fee_payer === AgencyFeePayer::Buyer) {
            return $agreement->transaction->buyer;
        }

        if ($agreement->seller !== null) {
            return $agreement->seller;
        }

        return $this->recipients->verifiedSellers($agreement->transaction->property)->first();
    }

    /**
     * @return Collection<int, User>
     */
    private function financeStaff(): Collection
    {
        return User::permission('staff.finance')->orderBy('id')->get();
    }

    /**
     * @param  array<string, string>  $context
     */
    private function desk(User $user, MessageEvent $event, array $context, string $subjectKey, ?Notification $inApp = null): void
    {
        app(MessageDesk::class)->send($user, $event, $context, $subjectKey, $inApp);
    }
}
