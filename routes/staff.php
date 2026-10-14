<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\CapabilityApplicationController;
use App\Http\Controllers\Staff\EnquiryController;
use App\Http\Controllers\WorkspaceDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'private.cache', 'workspace:staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
        Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');
        Route::put('/enquiries/{enquiry}/assignment', [EnquiryController::class, 'assign'])->name('enquiries.assign');
        Route::put('/enquiries/{enquiry}/status', [EnquiryController::class, 'update'])->name('enquiries.update');
        Route::post('/enquiries/{enquiry}/responses', [EnquiryController::class, 'respond'])->name('enquiries.respond');

        Route::middleware('permission:staff.brokerage')->group(function () {
            Route::get('/applications', [CapabilityApplicationController::class, 'index'])->name('applications.index');
            Route::get('/applications/{application}', [CapabilityApplicationController::class, 'edit'])->name('applications.edit');
            Route::put('/applications/{application}', [CapabilityApplicationController::class, 'update'])->name('applications.update');
        });
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
