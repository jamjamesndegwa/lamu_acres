<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\PartnerCapability;
use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\CapabilityApplication;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListPropertyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $sellerApplication = $user?->capabilityApplications()->where('capability', PartnerCapability::Seller)->first();
        $brokerApplication = $user?->capabilityApplications()->where('capability', PartnerCapability::Broker)->first();
        $hasBrokerAccess = $user?->hasPermission('workspace.broker') ?? false;
        $hasSellerAccess = $user?->hasPermission('workspace.seller') ?? false;

        return view('public.list-property', [
            'brokerApplication' => $brokerApplication,
            'sellerApplication' => $sellerApplication,
            'hasBrokerAccess' => $hasBrokerAccess,
            'hasSellerAccess' => $hasSellerAccess,
            'inProgress' => $this->inProgressProperties($user, $hasBrokerAccess, $hasSellerAccess),
            'primaryCta' => $this->primaryCta($user, $sellerApplication, $hasBrokerAccess, $hasSellerAccess),
        ]);
    }

    /**
     * @return array{type: string, label: string, helper: string, href?: string, requiresPurpose: bool}
     */
    private function primaryCta(?User $user, ?CapabilityApplication $sellerApplication, bool $hasBrokerAccess, bool $hasSellerAccess): array
    {
        if ($user === null) {
            return [
                'type' => 'start',
                'label' => 'Continue',
                'helper' => 'Sign in when prompted. New landowners are guided to the seller application; approved sellers go to property submission; brokers should use Broker Partners for sale or lease listings.',
                'requiresPurpose' => false,
            ];
        }

        if ($hasBrokerAccess) {
            return [
                'type' => 'start',
                'label' => 'Start a Property Listing',
                'helper' => 'Choose sale or lease, then continue to the broker submission form in your workspace.',
                'requiresPurpose' => true,
            ];
        }

        if ($hasSellerAccess) {
            return [
                'type' => 'direct',
                'href' => route('seller.properties.create'),
                'label' => 'Start a Property Listing',
                'helper' => 'Open property submission in your seller workspace. Staff review each listing before publication.',
                'requiresPurpose' => false,
            ];
        }

        if ($sellerApplication !== null) {
            return $this->sellerApplicationCta($sellerApplication);
        }

        return [
            'type' => 'direct',
            'href' => route('account.applications.create', ['capability' => 'seller']),
            'label' => 'Apply to List Your Property',
            'helper' => 'Submit a seller application before you can add property details.',
            'requiresPurpose' => false,
        ];
    }

    /**
     * @return array{type: string, label: string, helper: string, href: string, requiresPurpose: bool}
     */
    private function sellerApplicationCta(CapabilityApplication $application): array
    {
        $index = route('account.applications.index');
        $create = route('account.applications.create', ['capability' => 'seller']);
        $reason = filled($application->decision_reason) ? ' '.$application->decision_reason : '';

        return match ($application->status) {
            CapabilityApplicationStatus::Pending => [
                'type' => 'direct',
                'href' => $index,
                'label' => 'View Application Status',
                'helper' => 'Your seller application is awaiting staff review. Property submission opens after seller workspace access is approved.',
                'requiresPurpose' => false,
            ],
            CapabilityApplicationStatus::InformationRequired => [
                'type' => 'direct',
                'href' => $create,
                'label' => 'Provide Requested Information',
                'helper' => 'Staff requested more details for your seller application.'.$reason,
                'requiresPurpose' => false,
            ],
            CapabilityApplicationStatus::Rejected => [
                'type' => 'direct',
                'href' => $create,
                'label' => 'Review Decision and Reapply',
                'helper' => 'Your seller application was rejected.'.$reason.' You may submit an updated application.',
                'requiresPurpose' => false,
            ],
            CapabilityApplicationStatus::Approved => [
                'type' => 'direct',
                'href' => $index,
                'label' => 'View Application Status',
                'helper' => 'Your seller application is approved. If seller workspace access is not yet on your account, check your applications page for the latest status.',
                'requiresPurpose' => false,
            ],
            CapabilityApplicationStatus::Suspended => [
                'type' => 'direct',
                'href' => $index,
                'label' => 'View Application Status',
                'helper' => 'Your seller application is suspended.'.$reason,
                'requiresPurpose' => false,
            ],
        };
    }

    private function inProgressProperties(?User $user, bool $hasBrokerAccess, bool $hasSellerAccess)
    {
        if ($user === null) {
            return collect();
        }

        if ($hasBrokerAccess) {
            return Property::query()
                ->where('submitted_by', $user->id)
                ->where('status', '!=', PropertyStatus::Published)
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'status']);
        }

        if ($hasSellerAccess) {
            return Property::query()
                ->where('submitted_by', $user->id)
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'status']);
        }

        return collect();
    }
}
