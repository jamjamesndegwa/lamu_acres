<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PartnerCapability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\SafeListingReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request, SafeListingReturn $listingReturn): View
    {
        $capability = PartnerCapability::tryFrom((string) $request->query('capability', ''))?->value;

        return view('auth.login', [
            'capability' => $capability,
            'returnTo' => $listingReturn->remember($request),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $capability = PartnerCapability::tryFrom((string) $request->input('capability', ''));
        if ($capability !== null) {
            $request->session()->forget('url.intended');

            return redirect()->route('account.applications.create', ['capability' => $capability->value]);
        }

        return redirect()->intended($request->user()->workspaceHomeUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
