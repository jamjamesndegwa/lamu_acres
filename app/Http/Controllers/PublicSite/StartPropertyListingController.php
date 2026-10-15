<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\ListingPurpose;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StartPropertyListingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->hasPermission('workspace.broker')) {
            $request->validate([
                'purpose' => ['required', Rule::enum(ListingPurpose::class)],
            ]);

            return redirect()->route('broker.listings.create', [
                'purpose' => ListingPurpose::from($request->query('purpose'))->value,
            ]);
        }

        if ($request->user()->hasPermission('workspace.seller')) {
            return redirect()->route('seller.properties.create', array_filter([
                'purpose' => ListingPurpose::tryFrom((string) $request->query('purpose'))?->value,
            ]));
        }

        return redirect()
            ->route('account.applications.create', ['capability' => 'seller'])
            ->with('status', 'Apply for a seller workspace before submitting property. You choose sale or lease terms when you list the land.');
    }
}
