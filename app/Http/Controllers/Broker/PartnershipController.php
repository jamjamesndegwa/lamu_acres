<?php

namespace App\Http\Controllers\Broker;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\CommissionStatus;
use App\Enums\SourcingRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\BrokerIntroduction;
use App\Models\CommissionAgreement;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\SourcingMatch;
use App\Models\SourcingRequest;
use App\Services\Buyer\SourcingMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PartnershipController extends Controller
{
    public function requirements(Request $request): View
    {
        return view('broker.requirements.index', [
            'requests' => SourcingRequest::query()
                ->where('status', '!=', SourcingRequestStatus::Closed)
                ->latest('submitted_at')
                ->paginate(20),
        ]);
    }

    public function requirement(Request $request, SourcingRequest $sourcingRequest): View
    {
        abort_if($sourcingRequest->status === SourcingRequestStatus::Closed, 404);

        return view('broker.requirements.show', [
            'requirement' => $sourcingRequest,
            'listings' => Property::query()
                ->where('submitted_by', $request->user()->id)
                ->publiclyListed()
                ->orderBy('title')
                ->get(['id', 'title', 'reference']),
            'candidates' => SourcingMatch::query()
                ->where('sourcing_request_id', $sourcingRequest->id)
                ->whereHas('property', fn ($query) => $query->where('submitted_by', $request->user()->id))
                ->with('property:id,title,reference,slug')
                ->get(),
        ]);
    }

    public function propose(Request $request, SourcingRequest $sourcingRequest, SourcingMatcher $matcher): RedirectResponse
    {
        abort_if($sourcingRequest->status === SourcingRequestStatus::Closed, 404);

        $data = $request->validate([
            'property_id' => ['required', 'integer'],
        ]);

        $property = Property::query()
            ->whereKey($data['property_id'])
            ->where('submitted_by', $request->user()->id)
            ->publiclyListed()
            ->first();

        if ($property === null) {
            throw ValidationException::withMessages([
                'property_id' => 'Choose one of your published listings.',
            ]);
        }

        $existing = SourcingMatch::query()
            ->where('sourcing_request_id', $sourcingRequest->id)
            ->where('property_id', $property->id)
            ->whereNull('dismissed_at')
            ->exists();

        if ($existing) {
            return back()->with('status', 'This listing is already a candidate on the requirement.');
        }

        $sourcingRequest->load('amenities');
        $assessment = $matcher->assess($sourcingRequest, $property);

        if ($assessment === null) {
            throw ValidationException::withMessages([
                'property_id' => 'The recorded requirement does not accept this listing, so it was not added.',
            ]);
        }

        $sourcingRequest->matches()->create([
            'property_id' => $property->id,
            'band' => $assessment['band'],
            'distance_km' => $assessment['distance_km'],
            'reasons' => $assessment['reasons'],
        ]);

        return back()->with('status', 'Candidate submitted. Staff still decide whether the buyer sees it.');
    }

    public function deals(Request $request): View
    {
        return view('broker.deals.index', [
            'deals' => $this->deals($request)->with(['property:id,title,slug', 'introduction:id,reference,buyer_name'])->latest('opened_at')->paginate(20),
        ]);
    }

    public function deal(Request $request, SalesTransaction $salesTransaction): View
    {
        abort_unless($this->deals($request)->whereKey($salesTransaction->id)->exists(), 404);

        $salesTransaction->load([
            'property:id,title,slug,reference',
            'introduction:id,reference,buyer_name,status',
            'milestones',
            'agreements' => fn ($query) => $query->where('broker_id', $request->user()->id),
        ]);

        return view('broker.deals.show', ['deal' => $salesTransaction]);
    }

    public function agreements(Request $request): View
    {
        return view('broker.agreements.index', [
            'agreements' => $this->visibleAgreements($request)->with('property:id,title,slug')->latest('accepted_at')->paginate(20),
        ]);
    }

    public function agreement(Request $request, CommissionAgreement $commissionAgreement): View
    {
        abort_unless(
            (int) $commissionAgreement->broker_id === (int) $request->user()->id
            && $this->released($commissionAgreement->status),
            404,
        );

        $commissionAgreement->load(['property:id,title,reference', 'transaction:id,reference,stage', 'payment']);

        return view('broker.agreements.show', ['agreement' => $commissionAgreement]);
    }

    public function earnings(Request $request): View
    {
        $agreements = $this->visibleAgreements($request)->with('payment')->get();

        return view('broker.earnings.index', [
            'groups' => [
                'Earned' => $agreements->where('status', CommissionStatus::Earned),
                'Payable' => $agreements->where('status', CommissionStatus::Payable),
                'Paid' => $agreements->where('status', CommissionStatus::Paid),
                'Disputed' => $agreements->where('status', CommissionStatus::Disputed),
            ],
        ]);
    }

    private function deals(Request $request)
    {
        $introductions = BrokerIntroduction::query()
            ->where('broker_id', $request->user()->id)
            ->whereIn('status', [BrokerIntroductionStatus::Accepted, BrokerIntroductionStatus::Active])
            ->pluck('id');

        return SalesTransaction::query()->whereIn('broker_introduction_id', $introductions);
    }

    private function visibleAgreements(Request $request)
    {
        return CommissionAgreement::query()
            ->where('broker_id', $request->user()->id)
            ->whereIn('status', [
                CommissionStatus::Accepted,
                CommissionStatus::Earned,
                CommissionStatus::Payable,
                CommissionStatus::Paid,
                CommissionStatus::Disputed,
            ]);
    }

    private function released(CommissionStatus $status): bool
    {
        return in_array($status, [
            CommissionStatus::Accepted,
            CommissionStatus::Earned,
            CommissionStatus::Payable,
            CommissionStatus::Paid,
            CommissionStatus::Disputed,
        ], true);
    }
}
