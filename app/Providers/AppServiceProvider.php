<?php

namespace App\Providers;

use App\Contracts\Auth\OtpSender;
use App\Contracts\Communications\SmsSender;
use App\Contracts\Payments\MpesaGateway;
use App\Enums\Workspace;
use App\Listeners\RecordExternalNotificationMail;
use App\Services\Auth\UnconfiguredOtpSender;
use App\Services\Communications\HttpSmsSender;
use App\Services\Communications\UnconfiguredSmsSender;
use App\Services\Payments\DarajaMpesaGateway;
use App\Services\Payments\UnconfiguredMpesaGateway;
use App\Support\R2Storage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        R2Storage::mergePrivateDiskFromPublic();

        $this->app->bind(OtpSender::class, UnconfiguredOtpSender::class);
        $this->app->bind(SmsSender::class, function () {
            $sender = new HttpSmsSender;

            return $sender->configured() ? $sender : new UnconfiguredSmsSender;
        });
        $this->app->bind(MpesaGateway::class, function () {
            return config('mpesa.driver') === 'daraja'
                ? new DarajaMpesaGateway
                : new UnconfiguredMpesaGateway;
        });
    }

    public function boot(): void
    {
        if ($this->app->environment('local')) {
            $this->app->booted(function (): void {
                if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
                    return;
                }

                $rootUrl = request()->getSchemeAndHttpHost();

                if ($rootUrl !== '') {
                    URL::forceRootUrl($rootUrl);
                }
            });
        }

        $listener = RecordExternalNotificationMail::class;

        Event::listen(NotificationSending::class, [$listener, 'handleSending']);
        Event::listen(NotificationSent::class, [$listener, 'handleSent']);
        Event::listen(NotificationFailed::class, [$listener, 'handleFailed']);

        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.tailwind');

        RateLimiter::for('enquiries', function (Request $request): Limit {
            $key = (string) ($request->user()?->id ?: $request->ip());

            return Limit::perMinute(5)->by($key);
        });

        View::composer(['components.workspace-layout', 'components.public-layout', 'public.home'], function ($view): void {
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

            if ($user && $workspace) {
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
