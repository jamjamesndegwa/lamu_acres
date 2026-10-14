<?php

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

        Route::middleware('permission:admin.users')->group(function () {
            Route::get('/users/export', [UserAccessController::class, 'export'])->name('users.export');
            Route::get('/users', [UserAccessController::class, 'index'])->name('users.index');
            Route::get('/users/{user}/roles', [UserAccessController::class, 'edit'])->name('users.roles.edit');
            Route::put('/users/{user}/roles', [UserAccessController::class, 'update'])->name('users.roles.update');
            Route::get('/users/{user}/suspension', [UserAccessController::class, 'editSuspension'])->name('users.suspension.edit');
            Route::put('/users/{user}/suspension', [UserAccessController::class, 'updateSuspension'])->name('users.suspension.update');
        });
    });
