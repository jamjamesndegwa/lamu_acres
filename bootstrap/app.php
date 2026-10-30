<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureWorkspace;
use App\Http\Middleware\LogFileUploadFailures;
use App\Http\Middleware\PreventPrivateCaching;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(function () {
                require __DIR__.'/../routes/buyer.php';
                require __DIR__.'/../routes/broker.php';
                require __DIR__.'/../routes/seller.php';
                require __DIR__.'/../routes/investor.php';
                require __DIR__.'/../routes/staff.php';
                require __DIR__.'/../routes/admin.php';
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AuthenticateSession::class,
            EnsureAccountIsActive::class,
        ]);

        $middleware->prepend(LogFileUploadFailures::class);

        $middleware->alias([
            'workspace' => EnsureWorkspace::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'private.cache' => PreventPrivateCaching::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payments/mpesa/callback',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->redirectUsersTo(function (Request $request) {
            return $request->user()?->workspaceHomeUrl() ?? route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! config('app.friendly_error_pages') || $request->expectsJson()) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            if ($status !== 500) {
                return null;
            }

            return response()->view('errors.500', [], 500);
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Please refresh the page and try again.',
                ], 419);
            }

            $input = $request->except('password', 'password_confirmation', '_token');
            $sessionError = ['session' => 'Your session expired. Please submit the form again.'];

            if ($request->routeIs('register')) {
                return redirect()
                    ->route('register', array_filter($request->only(['capability', 'return_to'])))
                    ->withInput($input)
                    ->withErrors($sessionError);
            }

            if ($request->routeIs('login')) {
                return redirect()
                    ->route('login', array_filter($request->only(['capability', 'return_to'])))
                    ->withInput($input)
                    ->withErrors($sessionError);
            }

            if ($request->routeIs('password.email')) {
                return redirect()
                    ->route('password.request')
                    ->withInput($input)
                    ->withErrors($sessionError);
            }

            if ($request->routeIs('password.update')) {
                return redirect()
                    ->route('password.reset', ['token' => $request->input('token'), 'email' => $request->input('email')])
                    ->withInput($input)
                    ->withErrors($sessionError);
            }

            return redirect()
                ->back(fallback: route('home'))
                ->withInput($input)
                ->withErrors($sessionError);
        });
    })->create();
