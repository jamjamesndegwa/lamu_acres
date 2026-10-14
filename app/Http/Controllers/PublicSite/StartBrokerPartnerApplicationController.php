<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartBrokerPartnerApplicationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->hasPermission('workspace.broker')) {
            return redirect()->route('broker.listings.index');
        }

        return redirect()->route('account.applications.create', ['capability' => 'broker']);
    }
}
