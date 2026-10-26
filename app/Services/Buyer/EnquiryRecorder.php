<?php

namespace App\Services\Buyer;

use App\Enums\EnquiryStatus;
use App\Enums\LeadStatus;
use App\Events\EnquirySubmitted;
use App\Models\BrokerIntroduction;
use App\Models\Enquiry;
use App\Models\Offer;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Sales\SaleModeTerms;
use App\Services\Sales\SalesPipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnquiryRecorder
{
    public function submit(User $buyer, Property $property, string $message, Request $request): Enquiry
    {
        $message = trim($message);

        $this->guardAgainstDuplicate($buyer, $property, $message);
        $this->guardAgainstSpam($message);

        $enquiry = $this->createEnquiry($buyer, $property, $message, $request);

        event(new EnquirySubmitted($enquiry));

        return $enquiry;
    }

    public function submitPurchaseRequest(
        User $buyer,
        Property $property,
        int $requestedPrice,
        ?float $requestedAcreage,
        ?string $note,
        Request $request,
        SalesPipeline $pipeline,
    ): Enquiry {
        app(SaleModeTerms::class)->validatePurchaseAcreage($property, $requestedAcreage);

        $area = $requestedAcreage ?? $property->offeredAcreage();
        $message = 'Buyer request to purchase '.$property->title.'. Proposed price: KSh '.number_format($requestedPrice).'. Requested area: '.rtrim(rtrim(number_format($area, 4, '.', ''), '0'), '.').' acres.';

        $activePurchase = Enquiry::query()
            ->where('buyer_id', $buyer->id)
            ->where('property_id', $property->id)
            ->where('purchase_request', true)
            ->whereHas('salesTransaction', fn ($query) => $query->open())
            ->first();

        if ($activePurchase !== null) {
            throw ValidationException::withMessages([
                'requested_price' => 'You already have an active purchase request for this property. Open it from your enquiries to review its progress.',
            ]);
        }

        $openCase = SalesTransaction::query()
            ->where('buyer_id', $buyer->id)
            ->where('property_id', $property->id)
            ->open()
            ->with('enquiry')
            ->first();

        if ($openCase === null) {
            $linkedOffer = Offer::query()
                ->where('buyer_id', $buyer->id)
                ->where('property_id', $property->id)
                ->whereNotNull('sales_transaction_id')
                ->latest()
                ->first();

            if ($linkedOffer !== null) {
                $openCase = SalesTransaction::query()
                    ->whereKey($linkedOffer->sales_transaction_id)
                    ->open()
                    ->with('enquiry')
                    ->first();
            }
        }

        if ($openCase !== null && $openCase->enquiry_id !== null) {
            throw ValidationException::withMessages([
                'requested_price' => 'You already have an open sales case for this property. Open it from your enquiries to review its progress.',
            ]);
        }

        if (filled($note)) {
            $message .= "\n\nBuyer message: ".trim((string) $note);
        }

        $this->guardAgainstDuplicate($buyer, $property, $message);
        $this->guardAgainstSpam($message);

        return DB::transaction(function () use ($buyer, $property, $requestedPrice, $requestedAcreage, $area, $message, $request, $pipeline, $openCase): Enquiry {
            $enquiry = $this->createEnquiry(
                $buyer,
                $property,
                $message,
                $request,
                true,
                $requestedPrice,
                $requestedAcreage,
            );

            if ($openCase !== null) {
                $openCase->update(['enquiry_id' => $enquiry->id]);
                $transaction = $openCase->fresh();
                $pipeline->recordOffer($transaction, $buyer, (int) $transaction->lock_version, [
                    'amount' => $requestedPrice,
                    'note' => 'Buyer-submitted purchase request',
                ]);
            } else {
                $assignedTo = User::permission('staff.sales')->orderBy('id')->value('id')
                    ?? User::permission('staff.buyers')->orderBy('id')->value('id')
                    ?? $buyer->id;

                $transaction = $pipeline->open($buyer, [
                    'origin' => 'enquiry',
                    'property_id' => $property->id,
                    'enquiry_id' => $enquiry->id,
                    'assigned_to' => $assignedTo,
                    'acreage' => $area,
                ]);

                $pipeline->recordOffer($transaction, $buyer, 0, [
                    'amount' => $requestedPrice,
                    'note' => 'Buyer-submitted purchase request',
                ]);
            }

            event(new EnquirySubmitted($enquiry));

            return $enquiry;
        });
    }

    private function createEnquiry(
        User $buyer,
        Property $property,
        string $message,
        Request $request,
        bool $purchaseRequest = false,
        ?int $requestedPrice = null,
        ?float $requestedAcreage = null,
    ): Enquiry {
        return Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'broker_introduction_id' => $this->matchingIntroduction($buyer, $property)?->id,
            'status' => EnquiryStatus::New,
            'lead_status' => LeadStatus::New,
            'message' => $message,
            'purchase_request' => $purchaseRequest,
            'requested_price' => $requestedPrice,
            'requested_acreage' => $requestedAcreage,
            'sale_mode_at_request' => $purchaseRequest ? $property->saleMode()->value : null,
            'referrer_url' => $this->referrer($request, $property),
            'campaign' => $this->campaign($request),
            'source' => 'website',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'flagged_as_spam' => false,
        ]);
    }

    private function matchingIntroduction(User $buyer, Property $property): ?BrokerIntroduction
    {
        return BrokerIntroduction::query()
            ->activeForBuyer($buyer, $property)
            ->orderByRaw('case when property_id is null then 1 else 0 end')
            ->latest('introduced_at')
            ->first();
    }

    private function guardAgainstDuplicate(User $buyer, Property $property, string $message): void
    {
        $duplicate = Enquiry::query()
            ->where('buyer_id', $buyer->id)
            ->where('property_id', $property->id)
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'message' => 'You already sent this enquiry. Please wait before sending it again.',
            ]);
        }
    }

    private function guardAgainstSpam(string $message): void
    {
        $links = preg_match_all('/https?:\/\//i', $message);

        if ($links > 2) {
            throw ValidationException::withMessages([
                'message' => 'This enquiry could not be accepted.',
            ]);
        }
    }

    private function referrer(Request $request, Property $property): ?string
    {
        foreach ([$request->input('referrer_url'), $request->headers->get('referer')] as $value) {
            if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            if (str_contains($value, '/land/'.$property->slug)) {
                continue;
            }

            return substr($value, 0, 2048);
        }

        return null;
    }

    private function campaign(Request $request): ?string
    {
        $campaign = $request->input('campaign')
            ?: $request->query('utm_campaign')
            ?: $request->query('campaign');

        if (! is_string($campaign) || preg_match('/^[A-Za-z0-9._-]{1,80}$/', $campaign) !== 1) {
            return null;
        }

        return $campaign;
    }
}
