<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Subscription\Memberships;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function index(): View
    {
        abort_unless($this->allowed($request = request()), 403);

        return view('staff.memberships.index', [
            'subscriptions' => Subscription::query()->with(['user', 'plan', 'events'])->latest()->paginate(20),
        ]);
    }

    public function activate(Request $request, Subscription $subscription, Memberships $memberships): RedirectResponse
    {
        abort_unless($this->allowed($request), 403);
        $memberships->activate($request->user(), $subscription);

        return back()->with('status', 'Membership recorded. No payment was collected.');
    }

    public function renew(Request $request, Subscription $subscription, Memberships $memberships): RedirectResponse
    {
        abort_unless($this->allowed($request), 403);
        $memberships->renew($request->user(), $subscription);

        return back()->with('status', 'Membership renewed.');
    }

    public function cancel(Request $request, Subscription $subscription, Memberships $memberships): RedirectResponse
    {
        abort_unless($this->allowed($request), 403);
        $memberships->cancel($request->user(), $subscription);

        return back()->with('status', 'Membership cancelled.');
    }

    public function credit(Request $request, Subscription $subscription, Memberships $memberships): RedirectResponse
    {
        abort_unless($this->allowed($request), 403);

        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $memberships->grantCredit($request->user(), $subscription, (int) $data['days'], $data['reason']);

        return back()->with('status', 'Service credit recorded.');
    }

    private function allowed(Request $request): bool
    {
        return $request->user()->hasPermission('staff.finance') || $request->user()->hasPermission('admin.commercial');
    }
}
