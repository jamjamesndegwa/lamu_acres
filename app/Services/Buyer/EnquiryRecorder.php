<?php

namespace App\Services\Buyer;

use App\Enums\EnquiryStatus;
use App\Enums\LeadStatus;
use App\Events\EnquirySubmitted;
use App\Models\BrokerIntroduction;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnquiryRecorder
{
    public function submit(User $buyer, Property $property, string $message, Request $request): Enquiry
    {
        $message = trim($message);

        $this->guardAgainstDuplicate($buyer, $property, $message);
        $this->guardAgainstSpam($message);

        $introduction = $this->matchingIntroduction($buyer, $property);

        $enquiry = Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'broker_introduction_id' => $introduction?->id,
            'status' => EnquiryStatus::New,
            'lead_status' => LeadStatus::New,
            'message' => $message,
            'referrer_url' => $this->referrer($request, $property),
            'campaign' => $this->campaign($request),
            'source' => 'website',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'flagged_as_spam' => false,
        ]);

        event(new EnquirySubmitted($enquiry));

        return $enquiry;
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
