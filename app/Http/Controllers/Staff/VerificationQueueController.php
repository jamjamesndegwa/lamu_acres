<?php

namespace App\Http\Controllers\Staff;

use App\Enums\VerificationItemState;
use App\Enums\VerificationQueue;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\User;
use App\Models\VerificationItem;
use App\Services\Verification\VerificationOperations;
use App\Support\ApprovalsReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VerificationQueueController extends Controller
{
    public function __construct(private VerificationOperations $operations) {}

    public function newReviews(Request $request): View
    {
        return $this->properties($request, VerificationQueue::New, $this->operations->newReviews());
    }

    public function missing(Request $request): View
    {
        return $this->properties($request, VerificationQueue::Missing, $this->operations->missingDocuments());
    }

    public function exceptions(Request $request): View
    {
        return $this->properties($request, VerificationQueue::Exceptions, $this->operations->exceptions());
    }

    public function expiring(Request $request): View
    {
        $this->authorizeDesk($request);

        return view('staff.verification.queue', [
            'queue' => VerificationQueue::Expiring,
            'summary' => $this->operations->summary(),
            'rows' => $this->operations->expiringChecks()->with(['property', 'reviewer'])->paginate(20),
            'mode' => 'items',
        ]);
    }

    public function completed(Request $request): View
    {
        return $this->properties($request, VerificationQueue::Completed, $this->operations->completedReviews());
    }

    public function history(Request $request, Property $property): View
    {
        $this->authorizeDesk($request);
        $property->load(['passport.assignee', 'verificationReviews.actor', 'verificationItems']);

        return view('staff.verification.history', [
            'property' => $property,
            'officers' => User::permission('staff.verification')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function assign(Request $request, Property $property): RedirectResponse
    {
        $this->authorizeDesk($request);
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
            'deadline_at' => ['required', 'date', 'after:now'],
        ]);

        $passport = $property->passport()->firstOrFail();
        $this->operations->assign(
            $passport,
            User::query()->findOrFail($data['assigned_to']),
            $request->user(),
            Carbon::parse($data['deadline_at']),
        );

        return back()->with('status', 'Review assigned.');
    }

    public function decide(Request $request, VerificationItem $verificationItem): RedirectResponse
    {
        $this->authorizeDesk($request);
        $data = $request->validate([
            'state' => ['required', Rule::enum(VerificationItemState::class)],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
            'review_due_at' => ['nullable', 'date'],
        ]);

        $this->operations->decide(
            $verificationItem,
            $request->user(),
            VerificationItemState::from($data['state']),
            $data['reason'],
            $data['expires_at'] ?? null,
            $data['review_due_at'] ?? null,
        );

        return ApprovalsReturn::back($request, 'verification', 'Decision recorded and the verification level was recalculated.');
    }

    private function properties(Request $request, VerificationQueue $queue, Builder $query): View
    {
        $this->authorizeDesk($request);

        return view('staff.verification.queue', [
            'queue' => $queue,
            'summary' => $this->operations->summary(),
            'rows' => $query->with('passport')->latest()->paginate(20),
            'mode' => 'properties',
        ]);
    }

    private function authorizeDesk(Request $request): void
    {
        abort_unless($request->user()->hasPermission('staff.verification'), 403);
    }
}
