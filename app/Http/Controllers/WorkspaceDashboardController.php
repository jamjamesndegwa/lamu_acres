<?php

namespace App\Http\Controllers;

use App\Enums\OfferStatus;
use App\Enums\PropertyMatchStatus;
use App\Enums\SiteVisitStatus;
use App\Enums\SourcingRequestStatus;
use App\Enums\Workspace;
use App\Models\BuyerTimelineEntry;
use App\Models\Offer;
use App\Models\PropertyMatch;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\SourcingRequest;
use App\Services\Access\NavigationBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class WorkspaceDashboardController extends Controller
{
    public function __invoke(Request $request, NavigationBuilder $navigation): View
    {
        $workspace = Workspace::from((string) $request->session()->get('workspace'));
        $header = $navigation->header($request->user(), $workspace);
        $buyerId = $request->user()->id;

        return view('workspaces.dashboard', [
            'workspace' => $workspace,
            'heading' => $header['heading'],
            'intro' => $header['intro'],
            'panels' => $navigation->panels($request->user(), $workspace),
            'activityColumns' => config("workspaces.{$workspace->value}.activity.columns", []),
            'activityEmpty' => config("workspaces.{$workspace->value}.activity.empty"),
            'activity' => $workspace === Workspace::Buyer
                ? BuyerTimelineEntry::query()->where('buyer_id', $buyerId)->latest('occurred_at')->limit(8)->get()
                : new Collection,
            'progress' => $workspace === Workspace::Buyer ? [
                ['label' => 'Open searches', 'value' => SourcingRequest::query()->where('buyer_id', $buyerId)->where('status', '!=', SourcingRequestStatus::Closed)->count(), 'href' => route('buyer.sourcing.index')],
                ['label' => 'Shortlisted matches', 'value' => PropertyMatch::query()->where('buyer_id', $buyerId)->where('status', PropertyMatchStatus::Shortlisted)->count(), 'href' => route('buyer.matches.index')],
                ['label' => 'Upcoming visits', 'value' => SiteVisit::query()->where('buyer_id', $buyerId)->whereIn('status', [SiteVisitStatus::Requested, SiteVisitStatus::Confirmed])->count(), 'href' => route('buyer.visits.index')],
                ['label' => 'Open offers', 'value' => Offer::query()->where('buyer_id', $buyerId)->whereIn('status', [OfferStatus::Submitted, OfferStatus::UnderReview])->count(), 'href' => route('buyer.offers.index')],
            ] : null,
            'accessSummary' => $workspace === Workspace::Admin
                ? Role::query()->withCount('users')->orderBy('label')->get()
                : null,
        ]);
    }
}
