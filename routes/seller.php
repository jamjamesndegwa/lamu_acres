<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Seller\HoldingController;
use App\Http\Controllers\Seller\PropertyController;
use App\Http\Controllers\Seller\SalesController;
use App\Http\Controllers\WorkspaceDashboardController;
use App\Http\Controllers\WorkspaceNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'private.cache', 'workspace:seller'])
    ->prefix('seller')
    ->name('seller.')
    ->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
        Route::get('/holding', [HoldingController::class, 'edit'])->name('holding.edit');
        Route::put('/holding', [HoldingController::class, 'update'])->name('holding.update');
        Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
        Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create');
        Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
        Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
        Route::post('/properties/{property}/documents', [PropertyController::class, 'document'])->name('properties.documents.store');
        Route::get('/documents/{ownershipDocument}', [PropertyController::class, 'downloadDocument'])->name('documents.download');
        Route::post('/properties/{property}/authority', [PropertyController::class, 'authority'])->name('properties.authority');
        Route::post('/properties/{property}/instructions', [PropertyController::class, 'instruction'])->name('properties.instructions');
        Route::get('/enquiries', [SalesController::class, 'enquiries'])->name('enquiries.index');
        Route::get('/viewings', [SalesController::class, 'viewings'])->name('viewings.index');
        Route::post('/viewings/{siteVisit}', [SalesController::class, 'instructViewing'])->name('viewings.instruct');
        Route::get('/offers', [SalesController::class, 'offers'])->name('offers.index');
        Route::post('/offers/{offerPresentation}', [SalesController::class, 'respond'])->name('offers.respond');
        Route::get('/transactions', [SalesController::class, 'transactions'])->name('transactions.index');
        Route::get('/transactions/{propertyTransaction}', [SalesController::class, 'statement'])->name('transactions.show');
        Route::get('/notifications', [WorkspaceNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{notification}', [WorkspaceNotificationController::class, 'open'])->name('notifications.open');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
