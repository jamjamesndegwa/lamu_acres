<?php

namespace App\Providers;

use App\Contracts\Auth\OtpSender;
use App\Contracts\Payments\MpesaGateway;
use App\Enums\Workspace;
use App\Services\Auth\UnconfiguredOtpSender;
use App\Services\Payments\DarajaMpesaGateway;
use App\Services\Payments\UnconfiguredMpesaGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpSender::class, UnconfiguredOtpSender::class);
        $this->app->bind(MpesaGateway::class, function () {
            return config('mpesa.driver') === 'daraja'
                ? new DarajaMpesaGateway
                : new UnconfiguredMpesaGateway;
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.tailwind');

        RateLimiter::for('enquiries', function (Request $request): Limit {
            $key = (string) ($request->user()?->id ?: $request->ip());

            return Limit::perMinute(5)->by($key);
        });

        View::composer('components.workspace-layout', function ($view): void {
            $user = auth()->user();
            $current = session('workspace');
            $workspace = is_string($current) ? Workspace::tryFrom($current) : null;
            $accountRole = '';

            if ($user && $workspace === Workspace::Investor && $user->hasActiveInvestorPass()) {
                $accountRole = 'Investor Pass';
            } elseif ($user && $workspace) {
                $accountRole = $user->roles
                    ->where('workspace', $workspace->value)
                    ->pluck('label')
                    ->filter()
                    ->implode(', ');
            }

            $roleLabels = $accountRole === $workspace?->label() ? '' : $accountRole;

            $unreadNotifications = 0;

            if ($user && in_array($workspace, [Workspace::Broker, Workspace::Staff], true)) {
                $unreadNotifications = $user->unreadNotifications()->count();
            }

            $view->with([
                'currentWorkspace' => $workspace,
                'availableWorkspaces' => $user?->workspaces() ?? [],
                'workspaceRoles' => $roleLabels,
                'accountRole' => $accountRole,
                'unreadNotifications' => $unreadNotifications,
            ]);
        });
    }
}
