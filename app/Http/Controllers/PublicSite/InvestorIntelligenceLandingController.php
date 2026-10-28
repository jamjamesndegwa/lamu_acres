<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\ResearchReport;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscription\InvestorPassBilling;
use App\Services\Subscription\Memberships;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestorIntelligenceLandingController extends Controller
{
    public function __invoke(Request $request, Memberships $memberships, InvestorPassBilling $billing): View
    {
        $plan = SubscriptionPlan::investorPass();
        $user = $request->user();

        $checkoutReady = $billing->mpesaCheckoutReady($plan);
        $mpesaAmountKes = $billing->mpesaAmountKes($plan);

        $subscription = $user !== null ? $memberships->find($user, $plan) : null;
        if ($subscription !== null) {
            $memberships->expireIfDue($subscription);
            $subscription->refresh();
        }

        return view('public.investor-intelligence', [
            'plan' => $plan,
            'checkoutReady' => $checkoutReady,
            'mpesaAmountKes' => $mpesaAmountKes,
            'mpesaConversionSummary' => $billing->conversionSummary($plan),
            'mpesaConfigured' => config('mpesa.driver') !== 'unconfigured',
            'subscriptionCta' => $this->subscriptionCta($user, $subscription),
            'latestProjects' => DevelopmentProject::query()
                ->released()
                ->with(['county', 'locality'])
                ->latest('published_at')
                ->limit(3)
                ->get(),
            'latestReports' => ResearchReport::query()
                ->released()
                ->latest('published_at')
                ->limit(3)
                ->get(),
            'latestComparisons' => ImageryComparison::query()
                ->released()
                ->with(['beforeScene.county', 'beforeScene.locality', 'afterScene.county', 'afterScene.locality'])
                ->latest('published_at')
                ->limit(3)
                ->get(),
        ]);
    }

    /**
     * @return array{label: string, url: string}
     */
    private function subscriptionCta(?User $user, ?Subscription $subscription): array
    {
        if ($user === null) {
            return [
                'label' => 'Get Investor Pass',
                'url' => route('register'),
            ];
        }

        if ($user->hasActiveInvestorPass()) {
            return [
                'label' => 'Open Investor Dashboard',
                'url' => route('investor.dashboard'),
            ];
        }

        if ($user->hasPermission('workspace.buyer')) {
            return [
                'label' => $this->renewalLabel($subscription),
                'url' => route('buyer.subscription.show'),
            ];
        }

        if ($user->hasPermission('workspace.broker')) {
            return [
                'label' => $this->renewalLabel($subscription),
                'url' => route('broker.subscription.show'),
            ];
        }

        if ($user->hasPermission('workspace.seller')) {
            return [
                'label' => $this->renewalLabel($subscription),
                'url' => route('seller.subscription.show'),
            ];
        }

        return [
            'label' => 'Get Investor Pass',
            'url' => route('register'),
        ];
    }

    private function renewalLabel(?Subscription $subscription): string
    {
        if ($subscription === null) {
            return 'Subscribe to Investor Pass';
        }

        if (in_array($subscription->status, [SubscriptionStatus::Expired, SubscriptionStatus::Cancelled], true)) {
            return 'Renew Investor Pass';
        }

        if ($subscription->ends_at !== null && $subscription->ends_at->isPast()) {
            return 'Renew Investor Pass';
        }

        return 'Subscribe to Investor Pass';
    }
}
