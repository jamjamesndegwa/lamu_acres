<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\PartnerCapability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrokerPartnersController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('public.broker-partners', [
            'application' => $user?->capabilityApplications()->where('capability', PartnerCapability::Broker)->first(),
            'hasBrokerAccess' => $user?->hasPermission('workspace.broker') ?? false,
        ]);
    }
}
