<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfile;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyingProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorize('create', BuyerProfile::class);

        return view('buyer.buying-profile', [
            'profile' => $request->user()->buyerProfile,
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('create', BuyerProfile::class);

        $data = $request->validate([
            'looking_for' => ['required', 'string', 'max:255'],
            'preferred_locality' => ['nullable', 'string', 'max:120'],
        ]);

        $profile = $request->user()->buyerProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        $this->authorize('update', $profile);

        return redirect()
            ->route('buyer.dashboard')
            ->with('status', 'Buying profile saved.');
    }
}
