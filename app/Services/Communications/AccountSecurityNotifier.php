<?php

namespace App\Services\Communications;

use App\Enums\MessageEvent;
use App\Models\User;

class AccountSecurityNotifier
{
    public function passwordChanged(User $user): void
    {
        app(MessageDesk::class)->send($user, MessageEvent::PasswordChanged, [
            'name' => $user->name,
            'title' => 'Password updated',
            'summary' => 'Your Lamu Acres password was changed successfully. If you did not make this change, contact the desk immediately.',
            'url' => route('login'),
        ], 'password:changed:'.now()->timestamp);
    }

    public function accountSuspended(User $user, string $reason): void
    {
        app(MessageDesk::class)->send($user, MessageEvent::AccountSuspended, [
            'name' => $user->name,
            'title' => 'Account suspended',
            'summary' => 'Your Lamu Acres account has been suspended. '.$reason,
            'url' => route('login'),
        ], 'account:suspended:'.($user->suspended_at?->getTimestamp() ?? now()->timestamp));
    }

    public function accountRestored(User $user): void
    {
        app(MessageDesk::class)->send($user, MessageEvent::AccountRestored, [
            'name' => $user->name,
            'title' => 'Account restored',
            'summary' => 'Your Lamu Acres account access has been restored. You can sign in again.',
            'url' => route('login'),
        ], 'account:restored:'.now()->timestamp);
    }
}
