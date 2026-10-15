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
        Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
        Route::put('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
        Route::post('/properties/{property}/submission', [PropertyController::class, 'submit'])->name('properties.submission');
        Route::post('/properties/{property}/photos', [PropertyController::class, 'storePhoto'])->name('properties.photos.store');
        Route::put('/properties/{property}/photos/order', [PropertyController::class, 'reorderPhotos'])->name('properties.photos.order');
        Route::delete('/properties/{property}/photos/{propertyMedia}', [PropertyController::class, 'destroyPhoto'])->name('properties.photos.destroy');
        Route::put('/properties/{property}/photos/{propertyMedia}/cover', [PropertyController::class, 'coverPhoto'])->name('properties.photos.cover');
        Route::get('/properties/{property}/photos/{propertyMedia}', [PropertyController::class, 'previewPhoto'])->name('properties.photos.preview');
        Route::post('/properties/{property}/requests/{informationRequest}', [PropertyController::class, 'respond'])->name('properties.requests.respond');
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
