<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BrokerIntroductionStatus;
use App\Http\Controllers\Controller;
use App\Models\BrokerIntroduction;
use App\Services\Broker\ReferralRecorder;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReferralReviewController extends Controller
{
    public function index(): View
    {
        $this->authorize('review', BrokerIntroduction::query()->make());

        return view('staff.referrals.index', [
            'referrals' => BrokerIntroduction::query()
                ->with(['broker:id,name', 'properties:id,title'])
                ->latest('submitted_at')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(BrokerIntroduction $referral): View
    {
        $this->authorize('review', $referral);

        $referral->load(['broker', 'buyer', 'properties', 'audits.actor']);

        return view('staff.referrals.show', [
            'referral' => $referral,
        ]);
    }

    public function update(Request $request, BrokerIntroduction $referral, ReferralRecorder $recorder): RedirectResponse
    {
        $this->authorize('review', $referral);

        $data = $request->validate([
            'status' => ['required', Rule::enum(BrokerIntroductionStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = BrokerIntroductionStatus::from($data['status']);

        abort_unless(in_array($status, [
            BrokerIntroductionStatus::Review,
            BrokerIntroductionStatus::Accepted,
            BrokerIntroductionStatus::Rejected,
            BrokerIntroductionStatus::Disputed,
        ], true), 422);

        $recorder->decide($request->user(), $referral, $status, $data['note'] ?? null);

        return ApprovalsReturn::back($request, 'referrals', 'Referral marked '.$status->label().'.');
    }

    public function file(BrokerIntroduction $referral, string $kind): Response
    {
        $this->authorize('review', $referral);

        $path = $referral->privateFile($kind);
        abort_unless($path !== null, 404);

        $disk = $referral->privateFileDisk($kind);

        if ($disk !== 'local') {
            return redirect()->away(Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15)));
        }

        return Storage::disk($disk)->download($path, basename($path));
    }
}
