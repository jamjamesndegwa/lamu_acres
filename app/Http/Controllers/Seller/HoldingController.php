<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HoldingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->hasPermission('workspace.seller'), 403);

        return view('seller.profile', [
            'profile' => $request->user()->sellerProfile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('workspace.seller'), 403);

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:160'],
            'correspondence' => ['nullable', 'string', 'max:160'],
            'holding_note' => ['nullable', 'string', 'max:2000'],
        ]);

        SellerProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return back()->with('status', 'Seller profile saved.');
    }
}
