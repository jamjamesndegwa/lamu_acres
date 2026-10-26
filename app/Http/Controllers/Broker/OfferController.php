<?php

namespace App\Http\Controllers\Broker;

use App\Enums\BrokerIntroductionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broker\StoreBrokerOfferRequest;
use App\Models\BrokerIntroduction;
use App\Models\Property;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('create', BrokerIntroduction::class);

        $introductionId = (int) $request->query('introduction');
        $introduction = $introductionId > 0
            ? BrokerIntroduction::query()->where('broker_id', $request->user()->id)->find($introductionId)
            : null;

        return view('broker.offers.create', [
            'introduction' => $introduction,
            'referrals' => BrokerIntroduction::query()
                ->where('broker_id', $request->user()->id)
                ->whereNotNull('buyer_id')
                ->whereIn('status', [BrokerIntroductionStatus::Active, BrokerIntroductionStatus::Accepted])
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->with('properties:id,title')
                ->latest()
                ->limit(50)
                ->get(),
            'properties' => Property::query()->publiclyListed()->orderBy('title')->get(['id', 'title', 'reference']),
        ]);
    }

    public function store(StoreBrokerOfferRequest $request, InterestWorkflow $workflow): RedirectResponse
    {
        $introduction = BrokerIntroduction::query()
            ->where('broker_id', $request->user()->id)
            ->findOrFail($request->integer('broker_introduction_id'));

        $property = Property::query()->findOrFail($request->integer('property_id'));

        $workflow->submitBrokerOffer(
            $request->user(),
            $introduction,
            $property,
            $request->integer('amount'),
            $request->filled('requested_acreage') ? (float) $request->input('requested_acreage') : null,
            $request->string('message')->toString() ?: null,
        );

        return redirect()
            ->route('broker.referrals.show', $introduction)
            ->with('status', 'Offer submitted for your buyer. Lamu Acres staff will follow up.');
    }
}
