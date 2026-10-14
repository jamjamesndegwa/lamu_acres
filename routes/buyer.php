<?php

use App\Http\Controllers\Buyer\BuyingProfileController;
use App\Http\Controllers\Buyer\EnquiryController;
use App\Http\Controllers\Buyer\SavedPropertyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'private.cache', 'workspace:buyer'])
    ->prefix('buyer')
    ->name('buyer.')
    ->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
        Route::get('/saved', [SavedPropertyController::class, 'index'])->name('saved.index');
        Route::delete('/saved/{property}', [SavedPropertyController::class, 'destroy'])->name('saved.destroy');
        Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');
        Route::get('/buying-profile', [BuyingProfileController::class, 'edit'])->name('buying-profile');
        Route::put('/buying-profile', [BuyingProfileController::class, 'update'])->name('buying-profile.update');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
