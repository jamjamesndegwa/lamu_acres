<?php

namespace App\Http\Controllers;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\CommissionStatus;
use App\Enums\OfferStatus;
use App\Enums\PropertyMatchStatus;
use App\Enums\SiteVisitStatus;
use App\Enums\SourcingRequestStatus;
use App\Enums\Workspace;
use App\Models\BrokerIntroduction;
use App\Models\BrokerIntroductionAudit;
use App\Models\BuyerTimelineEntry;
use App\Models\CommissionAgreement;
use App\Models\DevelopmentProject;
use App\Models\Enquiry;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\PropertyMatch;
use App\Models\ResearchReport;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\SavedProperty;
use App\Models\SiteVisit;
use App\Models\SourcingRequest;
use App\Models\SubscriberWatch;
use App\Services\Access\NavigationBuilder;
use App\Services\Admin\ExecutiveReport;
use App\Services\Subscription\Memberships;
use App\Support\Reporting\ReportingWindow;
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
        $executiveDenied = $workspace === Workspace::Admin && ! $request->user()->hasPermission('admin.oversight');
        $window = null;
        $executive = null;

        if ($workspace === Workspace::Admin && $request->user()->hasPermission('admin.oversight')) {
            $window = ReportingWindow::fromRequest($request);
            $executive = ExecutiveReport::make($request->user(), $window);
        }
        $panels = $navigation->panels($request->user(), $workspace);

        if ($executive) {
            $panels = array_values(array_filter(
                $panels,
                fn (array $panel): bool => filled($panel['href']) && $panel['title'] !== 'Sales performance',
            ));
        }

        return view('workspaces.dashboard', [
            'workspace' => $workspace,
            'heading' => $header['heading'],
            'intro' => $executive
                ? 'Published listings, sales, demand, brokers, commissions, and subscriptions from the records you are allowed to see.'
                : $header['intro'],
            'panels' => $panels,
            'executive' => $executive,
            'executiveDenied' => $executiveDenied,
            'window' => $window,
            'activityColumns' => config("workspaces.{$workspace->value}.activity.columns", []),
            'activityEmpty' => config("workspaces.{$workspace->value}.activity.empty"),
            'activity' => match ($workspace) {
                Workspace::Buyer => BuyerTimelineEntry::query()->where('buyer_id', $buyerId)->latest('occurred_at')->limit(8)->get(),
                Workspace::Broker => $this->brokerActivity($buyerId),
                default => new Collection,
            },
            'progress' => match ($workspace) {
                Workspace::Buyer => $this->buyerProgress($buyerId),
                Workspace::Broker => $this->brokerProgress($buyerId),
                default => null,
            },
            'nextAction' => match ($workspace) {
                Workspace::Buyer => $this->buyerNextAction($buyerId),
                Workspace::Broker => $this->brokerNextAction($buyerId),
                default => null,
            },
            'accessSummary' => $workspace === Workspace::Admin
                ? Role::query()->withCount('users')->orderBy('label')->get()
                : null,
            'investor' => $workspace === Workspace::Investor ? $this->investorBrief($request, app(Memberships::class)) : null,
        ]);
    }

    /**
     * @return array{premium: bool, ends: mixed, reports: Collection, projects: Collection, watches: Collection}
     */
    private function investorBrief(Request $request, Memberships $memberships): array
    {
        $user = $request->user();
        $subscription = $memberships->find($user);

        return [
            'premium' => $user->hasActiveInvestorPass(),
            'ends' => $subscription?->ends_at,
            'status' => $subscription?->status,
            'reports' => ResearchReport::query()->released()->with(['projects', 'citations'])->latest('published_at')->limit(4)->get(),
            'projects' => DevelopmentProject::query()->released()->latest('published_at')->limit(4)->get(),
            'watches' => SubscriberWatch::query()->where('user_id', $user->id)->with(['project', 'locality'])->latest()->limit(6)->get(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, href: string}>
     */
    private function buyerProgress(int $buyerId): array
    {
        return [
            ['label' => 'Open searches', 'value' => SourcingRequest::query()->where('buyer_id', $buyerId)->where('status', '!=', SourcingRequestStatus::Closed)->count(), 'href' => route('buyer.sourcing.index')],
            ['label' => 'Saved properties', 'value' => SavedProperty::query()->where('user_id', $buyerId)->count(), 'href' => route('buyer.saved.index')],
            ['label' => 'Shortlisted matches', 'value' => PropertyMatch::query()->where('buyer_id', $buyerId)->where('status', PropertyMatchStatus::Shortlisted)->count(), 'href' => route('buyer.matches.index')],
            ['label' => 'Enquiries', 'value' => Enquiry::query()->where('buyer_id', $buyerId)->count(), 'href' => route('buyer.enquiries.index')],
            ['label' => 'Upcoming visits', 'value' => SiteVisit::query()->where('buyer_id', $buyerId)->whereIn('status', [SiteVisitStatus::Requested, SiteVisitStatus::Confirmed])->count(), 'href' => route('buyer.visits.index')],
            ['label' => 'Open offers', 'value' => Offer::query()->where('buyer_id', $buyerId)->whereIn('status', [OfferStatus::Submitted, OfferStatus::UnderReview])->count(), 'href' => route('buyer.offers.index')],
        ];
    }

    /**
     * @return array{label: string, detail: string, href: string}
     */
    private function buyerNextAction(int $buyerId): array
    {
        $searches = SourcingRequest::query()->where('buyer_id', $buyerId)->where('status', '!=', SourcingRequestStatus::Closed)->count();
        $matches = PropertyMatch::query()->where('buyer_id', $buyerId)->where('status', PropertyMatchStatus::Shortlisted)->count();
        $visits = SiteVisit::query()->where('buyer_id', $buyerId)->whereIn('status', [SiteVisitStatus::Requested, SiteVisitStatus::Confirmed])->count();
        $offers = Offer::query()->where('buyer_id', $buyerId)->whereIn('status', [OfferStatus::Submitted, OfferStatus::UnderReview])->count();
        $saved = SavedProperty::query()->where('user_id', $buyerId)->count();
        $enquiries = Enquiry::query()->where('buyer_id', $buyerId)->count();

        if ($searches === 0 && $saved === 0) {
            return ['label' => 'Find land for me', 'detail' => 'Choose an area on the map and record the size, budget, and use you want.', 'href' => route('buyer.sourcing.create'), 'frame' => 'modal'];
        }

        if ($matches > 0) {
            return ['label' => 'Review your matches', 'detail' => 'Compare the shortlist, then enquire, book a visit, or make an offer.', 'href' => route('buyer.matches.index')];
        }

        if ($visits > 0) {
            return ['label' => 'Check your site visits', 'detail' => 'Confirm the appointment or acknowledge a viewing you have attended.', 'href' => route('buyer.visits.index')];
        }

        if ($offers > 0) {
            return ['label' => 'Track your offers', 'detail' => 'See the status of each offer. You can withdraw one that is still open.', 'href' => route('buyer.offers.index')];
        }

        if ($saved > 0 && $enquiries === 0) {
            return ['label' => 'Enquire about saved land', 'detail' => 'Open a saved property and send a question to the desk.', 'href' => route('buyer.saved.index')];
        }

        if ($searches > 0 && $matches === 0) {
            return ['label' => 'Browse while matching continues', 'detail' => 'No approved match is ready yet. You can explore published land or adjust the search.', 'href' => route('land.index')];
        }

        return ['label' => 'Explore land', 'detail' => 'Open the directory and save a property you want to follow.', 'href' => route('land.index')];
    }

    /**
     * @return array<int, array{label: string, value: int|string, href: string}>
     */
    private function brokerProgress(int $brokerId): array
    {
        $introductions = BrokerIntroduction::query()
            ->where('broker_id', $brokerId)
            ->whereIn('status', [BrokerIntroductionStatus::Accepted, BrokerIntroductionStatus::Active])
            ->pluck('id');

        $agreements = CommissionAgreement::query()
            ->where('broker_id', $brokerId)
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid, CommissionStatus::Disputed]);

        return [
            ['label' => 'Listings', 'value' => Property::query()->where('submitted_by', $brokerId)->count(), 'href' => route('broker.listings.index')],
            ['label' => 'Referrals', 'value' => BrokerIntroduction::query()->where('broker_id', $brokerId)->count(), 'href' => route('broker.referrals.index')],
            ['label' => 'Open deals', 'value' => SalesTransaction::query()->whereIn('broker_introduction_id', $introductions)->where('stage', '!=', 'completed')->count(), 'href' => route('broker.deals.index')],
            ['label' => 'Commission records', 'value' => (clone $agreements)->count(), 'href' => route('broker.earnings.index')],
        ];
    }

    /**
     * @return array{label: string, detail: string, href: string}
     */
    private function brokerNextAction(int $brokerId): array
    {
        $listings = Property::query()->where('submitted_by', $brokerId)->count();
        $referrals = BrokerIntroduction::query()->where('broker_id', $brokerId)->count();

        if ($listings === 0) {
            return ['label' => 'Submit a property', 'detail' => 'Send land you are authorised to market. It stays unpublished until staff approve it.', 'href' => route('broker.listings.create')];
        }

        if ($referrals === 0) {
            return ['label' => 'Register a buyer', 'detail' => 'Record an introduction against a published property, with the buyer’s consent.', 'href' => route('broker.referrals.create')];
        }

        return ['label' => 'Review your referrals', 'detail' => 'Staff review conflicts before commission protection starts.', 'href' => route('broker.referrals.index')];
    }

    private function brokerActivity(int $brokerId): Collection
    {
        $listingIds = Property::query()->where('submitted_by', $brokerId)->pluck('id');
        $referralIds = BrokerIntroduction::query()->where('broker_id', $brokerId)->pluck('id');

        $listings = PropertyAudit::query()
            ->whereIn('property_id', $listingIds)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PropertyAudit $audit): object => (object) [
                'summary' => 'Listing '.$audit->action,
                'occurred_at' => $audit->created_at,
            ]);

        $referrals = BrokerIntroductionAudit::query()
            ->whereIn('broker_introduction_id', $referralIds)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (BrokerIntroductionAudit $audit): object => (object) [
                'summary' => 'Referral '.$audit->action,
                'occurred_at' => $audit->created_at,
            ]);

        return $listings->concat($referrals)->sortByDesc('occurred_at')->take(8)->values();
    }
}
