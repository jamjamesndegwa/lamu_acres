<?php

use App\Http\Controllers\Account\MembershipController;
use App\Http\Controllers\Buyer\BuyingProfileController;
use App\Http\Controllers\Buyer\EnquiryController;
use App\Http\Controllers\Buyer\MatchController;
use App\Http\Controllers\Buyer\OfferController;
use App\Http\Controllers\Buyer\SavedPropertyController;
use App\Http\Controllers\Buyer\SiteVisitController;
use App\Http\Controllers\Buyer\SourcingRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceDashboardController;
use App\Http\Controllers\WorkspaceNotificationController;
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
        Route::get('/sourcing', [SourcingRequestController::class, 'index'])->name('sourcing.index');
        Route::get('/sourcing/create', [SourcingRequestController::class, 'create'])->name('sourcing.create');
        Route::post('/sourcing', [SourcingRequestController::class, 'store'])->name('sourcing.store');
        Route::get('/sourcing/{sourcingRequest}', [SourcingRequestController::class, 'show'])->name('sourcing.show');
        Route::get('/sourcing/{sourcingRequest}/edit', [SourcingRequestController::class, 'edit'])->name('sourcing.edit');
        Route::put('/sourcing/{sourcingRequest}', [SourcingRequestController::class, 'update'])->name('sourcing.update');
        Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');
        Route::get('/matches/{propertyMatch}', [MatchController::class, 'show'])->name('matches.show');
        Route::post('/matches/{propertyMatch}/rejection', [MatchController::class, 'reject'])->name('matches.reject');
        Route::post('/matches/{propertyMatch}/visits', [MatchController::class, 'visit'])->name('matches.visits.store');
        Route::post('/matches/{propertyMatch}/offers', [MatchController::class, 'offer'])->name('matches.offers.store');
        Route::post('/matches/{propertyMatch}/documents', [MatchController::class, 'documents'])->name('matches.documents.store');
        Route::get('/visits', [SiteVisitController::class, 'index'])->name('visits.index');
        Route::post('/visits/{siteVisit}/cancel', [SiteVisitController::class, 'cancel'])->name('visits.cancel');
        Route::post('/visits/{siteVisit}/acknowledgment', [SiteVisitController::class, 'acknowledge'])->name('visits.acknowledge');
        Route::post('/visits/{siteVisit}/confirmation', [SiteVisitController::class, 'confirmDetails'])->name('visits.confirm-details');
        Route::post('/visits/{siteVisit}/reschedule', [SiteVisitController::class, 'requestReschedule'])->name('visits.reschedule');
        Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
        Route::post('/offers/{offer}/withdraw', [OfferController::class, 'withdraw'])->name('offers.withdraw');
        Route::get('/buying-profile', [BuyingProfileController::class, 'edit'])->name('buying-profile');
        Route::put('/buying-profile', [BuyingProfileController::class, 'update'])->name('buying-profile.update');
        Route::get('/subscription', [MembershipController::class, 'show'])->name('subscription.show');
        Route::post('/subscription', [MembershipController::class, 'store'])->name('subscription.store');
        Route::post('/subscription/cancellation', [MembershipController::class, 'cancel'])->name('subscription.cancel');
        Route::post('/subscription/checkout', [MembershipController::class, 'checkout'])->name('subscription.checkout');
        Route::post('/subscription/payments/{subscriptionPayment}/retry', [MembershipController::class, 'retry'])->name('subscription.retry');
        Route::get('/notifications', [WorkspaceNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [WorkspaceNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('/notifications/{notification}', [WorkspaceNotificationController::class, 'open'])->name('notifications.open');
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
