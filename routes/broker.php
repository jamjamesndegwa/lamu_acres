<?php

use App\Http\Controllers\Account\MembershipController;
use App\Http\Controllers\Broker\ListingController;
use App\Http\Controllers\Broker\ListingMediaController;
use App\Http\Controllers\Broker\OfferController;
use App\Http\Controllers\Broker\PartnershipController;
use App\Http\Controllers\Broker\ReferralController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceDashboardController;
use App\Http\Controllers\WorkspaceNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'private.cache', 'workspace:broker'])
    ->prefix('broker')
    ->name('broker.')
    ->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
        Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
        Route::get('/listings/create', [ListingController::class, 'create'])->name('listings.create');
        Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
        Route::get('/listings/{property}', [ListingController::class, 'show'])->name('listings.show');
        Route::get('/listings/{property}/media/{propertyMedia}', [ListingController::class, 'media'])->name('listings.media');
        Route::post('/listings/{property}/photos', [ListingMediaController::class, 'store'])->name('listings.photos.store');
        Route::put('/listings/{property}/media/order', [ListingMediaController::class, 'reorder'])->name('listings.media.order');
        Route::delete('/listings/{property}/media/{propertyMedia}', [ListingMediaController::class, 'destroy'])->name('listings.media.destroy');
        Route::post('/listings/{property}/requests/{informationRequest}', [ListingController::class, 'respond'])->name('listings.respond');
        Route::get('/offers/create', [OfferController::class, 'create'])->name('offers.create');
        Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
        Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
        Route::get('/referrals/create', [ReferralController::class, 'create'])->name('referrals.create');
        Route::post('/referrals', [ReferralController::class, 'store'])->name('referrals.store');
        Route::get('/referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show');
        Route::get('/referrals/{referral}/files/{kind}', [ReferralController::class, 'file'])
            ->whereIn('kind', ['consent', 'evidence'])
            ->name('referrals.file');
        Route::get('/requirements', [PartnershipController::class, 'requirements'])->name('requirements.index');
        Route::get('/requirements/{sourcingRequest}', [PartnershipController::class, 'requirement'])->name('requirements.show');
        Route::post('/requirements/{sourcingRequest}/candidates', [PartnershipController::class, 'propose'])->name('requirements.propose');
        Route::get('/deals', [PartnershipController::class, 'deals'])->name('deals.index');
        Route::get('/deals/{salesTransaction}', [PartnershipController::class, 'deal'])->name('deals.show');
        Route::get('/agreements', [PartnershipController::class, 'agreements'])->name('agreements.index');
        Route::get('/agreements/{commissionAgreement}', [PartnershipController::class, 'agreement'])->name('agreements.show');
        Route::get('/earnings', [PartnershipController::class, 'earnings'])->name('earnings.index');
        Route::get('/notifications', [WorkspaceNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [WorkspaceNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('/notifications/{notification}', [WorkspaceNotificationController::class, 'open'])->name('notifications.open');
        Route::get('/subscription', [MembershipController::class, 'show'])->name('subscription.show');
        Route::post('/subscription', [MembershipController::class, 'store'])->name('subscription.store');
        Route::post('/subscription/cancellation', [MembershipController::class, 'cancel'])->name('subscription.cancel');
        Route::post('/subscription/checkout', [MembershipController::class, 'checkout'])->name('subscription.checkout');
        Route::post('/subscription/payments/{subscriptionPayment}/retry', [MembershipController::class, 'retry'])->name('subscription.retry');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
