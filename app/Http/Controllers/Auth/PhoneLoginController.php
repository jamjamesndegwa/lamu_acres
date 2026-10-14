<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\OtpLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PhoneLoginController extends Controller
{
    public function store(Request $request, OtpLogin $otp): RedirectResponse
    {
        $phone = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ])['phone'];

        $delivery = $otp->request($phone);

        if (! $delivery->delivered) {
            throw ValidationException::withMessages([
                'phone' => 'Phone sign-in is not available until a delivery provider is configured.',
            ]);
        }

        return back()->with('status', 'Enter the code sent to your phone.');
    }

    public function verify(Request $request, OtpLogin $otp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $user = $otp->verify($data['phone'], $data['code']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => 'That code could not be confirmed.',
            ]);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'code' => 'This account is suspended.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended($user->workspaceHomeUrl());
    }
}
