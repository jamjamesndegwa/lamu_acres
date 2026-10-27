<?php

namespace App\Http\Controllers\Communications;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    public function show(string $token): View
    {
        $user = User::query()->where('communications_unsubscribe_token', $token)->firstOrFail();

        return view('communications.unsubscribe', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $user = User::query()->where('communications_unsubscribe_token', $token)->firstOrFail();

        $user->forceFill([
            'marketing_email_consent' => false,
            'marketing_sms_consent' => false,
        ])->save();

        return redirect()
            ->route('communications.unsubscribe', $token)
            ->with('status', 'You have been unsubscribed from Lamu Acres marketing messages.');
    }
}
