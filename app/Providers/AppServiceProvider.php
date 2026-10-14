<?php

namespace App\Providers;

use App\Contracts\Auth\OtpSender;
use App\Enums\Workspace;
use App\Services\Auth\UnconfiguredOtpSender;
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
            $roleLabels = '';

            if ($user && $workspace === Workspace::Investor && $user->hasActiveInvestorPass()) {
                $roleLabels = 'Investor Pass';
            } elseif ($user && $workspace) {
                $roleLabels = $user->roles
                    ->where('workspace', $workspace->value)
                    ->pluck('label')
                    ->filter()
                    ->implode(', ');

                if ($roleLabels === $workspace->label()) {
                    $roleLabels = '';
                }
            }

            $view->with([
                'currentWorkspace' => $workspace,
                'availableWorkspaces' => $user?->workspaces() ?? [],
                'workspaceRoles' => $roleLabels,
            ]);
        });
    }
}
