<?php

use App\Http\Controllers\Account\MembershipController;
use App\Http\Controllers\Intelligence\LibraryController;
use App\Http\Controllers\Intelligence\WatchlistController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceDashboardController;
use App\Http\Controllers\WorkspaceNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'private.cache', 'workspace:investor'])
    ->prefix('investor')
    ->name('investor.')
    ->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
        Route::get('/developments', [LibraryController::class, 'projects'])->name('projects.index');
        Route::get('/developments/{project}', [LibraryController::class, 'project'])->name('projects.show');
        Route::get('/imagery', [LibraryController::class, 'imagery'])->name('imagery.index');
        Route::get('/imagery/{comparison}', [LibraryController::class, 'comparison'])->name('imagery.show');
        Route::get('/planning', [LibraryController::class, 'planning'])->name('planning.index');
        Route::get('/planning/{record}', [LibraryController::class, 'plan'])->name('planning.show');
        Route::get('/prices', [LibraryController::class, 'prices'])->name('prices');
        Route::get('/reports', [LibraryController::class, 'reports'])->name('reports.index');
        Route::get('/reports/{report}', [LibraryController::class, 'report'])->name('reports.show');
        Route::get('/watchlists', [WatchlistController::class, 'index'])->name('watchlists.index');
        Route::post('/watchlists', [WatchlistController::class, 'store'])->name('watchlists.store');
        Route::get('/archive', [WatchlistController::class, 'archive'])->name('archive.index');
        Route::get('/subscription', [MembershipController::class, 'show'])->name('subscription.show');
        Route::post('/subscription/cancellation', [MembershipController::class, 'cancel'])->name('subscription.cancel');
        Route::get('/notifications', [WorkspaceNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{notification}', [WorkspaceNotificationController::class, 'open'])->name('notifications.open');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
