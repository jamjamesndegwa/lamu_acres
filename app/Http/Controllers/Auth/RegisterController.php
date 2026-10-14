<?php

namespace App\Http\Controllers\Auth;

use App\Enums\MessageEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Access\RoleAssigner;
use App\Services\Communications\MessageDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, RoleAssigner $roles): RedirectResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'email', 'password']));

        $roles->assign($user, 'buyer');

        Auth::login($user);

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome to Lamu Acres',
            'summary' => 'Your buyer account is ready.',
            'url' => route('buyer.dashboard'),
        ], 'user:'.$user->id);

        $request->session()->regenerate();

        return redirect()->route('buyer.dashboard');
    }
}
