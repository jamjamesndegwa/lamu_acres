<?php

namespace App\Http\Controllers\Broker;

use App\Http\Controllers\Controller;
use App\Models\BrokerIntroduction;
use App\Models\Property;
use App\Services\Broker\ReferralConflictDetector;
use App\Services\Broker\ReferralRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferralController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', BrokerIntroduction::class);

        return view('broker.referrals.index', [
            'referrals' => BrokerIntroduction::query()
                ->where('broker_id', $request->user()->id)
                ->with('properties:id,title,slug')
                ->latest('submitted_at')
                ->latest()
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BrokerIntroduction::class);

        return view('broker.referrals.create', [
            'properties' => Property::query()->publiclyListed()->orderBy('title')->get(['id', 'title', 'reference', 'locality', 'county']),
        ]);
    }

    public function store(Request $request, ReferralRecorder $recorder): RedirectResponse
    {
        $this->authorize('create', BrokerIntroduction::class);

        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:160'],
            'buyer_email' => ['required', 'email', 'max:160'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
            'property_ids' => ['required', 'array', 'min:1'],
            'property_ids.*' => ['integer', Rule::exists('properties', 'id')],
            'introduced_at' => ['required', 'date', 'before_or_equal:today'],
            'source' => ['required', 'string', 'max:120'],
            'commission_terms' => ['required', 'string', 'max:2000'],
            'consent_note' => ['required', 'string', 'min:20', 'max:2000'],
            'consent_evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $eligible = Property::query()->publiclyListed()->whereIn('id', $data['property_ids'])->pluck('id')->all();

        if (count($eligible) !== count(array_unique($data['property_ids']))) {
            return back()->withInput()->withErrors([
                'property_ids' => 'Choose published properties only.',
            ]);
        }

        $introduction = $recorder->submit(
            $request->user(),
            $data,
            $eligible,
            $request->file('consent_evidence'),
            $request->file('evidence'),
        );

        return redirect()
            ->route('broker.referrals.show', $introduction)
            ->with('status', 'Referral submitted. Staff will review it. Commission is not protected yet.');
    }

    public function show(Request $request, BrokerIntroduction $referral, ReferralConflictDetector $conflicts): View
    {
        abort_unless($referral->broker_id === $request->user()->id, 404);

        $referral->load(['properties:id,title,reference,locality,county', 'audits']);

        return view('broker.referrals.show', [
            'referral' => $referral,
            'messages' => $conflicts->brokerMessages($referral->conflicts ?? []),
        ]);
    }

    public function file(Request $request, BrokerIntroduction $referral, string $kind): StreamedResponse
    {
        abort_unless($referral->broker_id === $request->user()->id, 404);

        $path = $referral->privateFile($kind);
        abort_unless($path !== null, 404);

        return Storage::disk('local')->download($path, basename($path));
    }
}
