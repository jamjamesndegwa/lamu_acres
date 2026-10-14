<?php

use App\Http\Controllers\Admin\GeographyController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'private.cache', 'workspace:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::middleware('permission:admin.geography')->group(function () {
            Route::get('/geography', [GeographyController::class, 'index'])->name('geography.index');
            Route::get('/geography/counties/create', [GeographyController::class, 'createCounty'])->name('geography.counties.create');
            Route::post('/geography/counties', [GeographyController::class, 'storeCounty'])->name('geography.counties.store');
            Route::get('/geography/counties/{county}', [GeographyController::class, 'showCounty'])->name('geography.counties.show');
            Route::get('/geography/counties/{county}/edit', [GeographyController::class, 'editCounty'])->name('geography.counties.edit');
            Route::put('/geography/counties/{county}', [GeographyController::class, 'updateCounty'])->name('geography.counties.update');
            Route::get('/geography/counties/{county}/localities/create', [GeographyController::class, 'createLocality'])->name('geography.localities.create');
            Route::post('/geography/counties/{county}/localities', [GeographyController::class, 'storeLocality'])->name('geography.localities.store');
            Route::get('/geography/localities/{locality}', [GeographyController::class, 'editLocality'])->name('geography.localities.edit');
            Route::put('/geography/localities/{locality}', [GeographyController::class, 'updateLocality'])->name('geography.localities.update');
            Route::post('/geography/localities/{locality}/aliases', [GeographyController::class, 'storeAlias'])->name('geography.aliases.store');
        });

        Route::middleware('permission:admin.commercial')->group(function () {
            Route::get('/plans', [SubscriptionPlanController::class, 'edit'])->name('plans.edit');
            Route::put('/plans', [SubscriptionPlanController::class, 'update'])->name('plans.update');
        });

        Route::middleware('permission:admin.users')->group(function () {
            Route::get('/users/export', [UserAccessController::class, 'export'])->name('users.export');
            Route::get('/users', [UserAccessController::class, 'index'])->name('users.index');
            Route::get('/users/{user}/roles', [UserAccessController::class, 'edit'])->name('users.roles.edit');
            Route::put('/users/{user}/roles', [UserAccessController::class, 'update'])->name('users.roles.update');
            Route::get('/users/{user}/suspension', [UserAccessController::class, 'editSuspension'])->name('users.suspension.edit');
            Route::put('/users/{user}/suspension', [UserAccessController::class, 'updateSuspension'])->name('users.suspension.update');
        });
    });
