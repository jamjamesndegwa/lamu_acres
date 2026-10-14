<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\CapabilityApplicationController;
use App\Http\Controllers\Staff\DocumentAccessController;
use App\Http\Controllers\Staff\EnquiryController;
use App\Http\Controllers\Staff\OfferController;
use App\Http\Controllers\Staff\SellerDeskController;
use App\Http\Controllers\Staff\SiteVisitController;
use App\Http\Controllers\Staff\SourcingQueueController;
use App\Http\Controllers\Staff\VerificationController;
use App\Http\Controllers\Staff\VerificationQueueController;
use App\Http\Controllers\Staff\SalesPipelineController;
use App\Http\Controllers\Staff\CommissionAgreementController;
use App\Http\Controllers\Staff\ClosingController;
use App\Http\Controllers\Staff\CommunicationsController;
use App\Http\Controllers\Staff\SettlementController;
use App\Http\Controllers\Staff\DevelopmentProjectController;
use App\Http\Controllers\Staff\ImageryController;
use App\Http\Controllers\Staff\ListingReviewController;
use App\Http\Controllers\Staff\MembershipController;
use App\Http\Controllers\Staff\PlanningRecordController;
use App\Http\Controllers\Staff\ResearchCommitmentController;
use App\Http\Controllers\Staff\ResearchReportController;
use App\Http\Controllers\Staff\VerifiedSaleController;
use App\Http\Controllers\Staff\PropertyInventoryController;
use App\Http\Controllers\Staff\PropertyMediaController;
use App\Http\Controllers\Staff\ReferralReviewController;
use App\Http\Controllers\Staff\OverviewController;
use App\Http\Controllers\WorkspaceDashboardController;
use App\Http\Controllers\WorkspaceNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'private.cache', 'workspace:staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
        Route::get('/tasks', [OverviewController::class, 'tasks'])->name('tasks');
        Route::get('/calendar', [OverviewController::class, 'calendar'])->name('calendar');
        Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');
        Route::put('/enquiries/{enquiry}/assignment', [EnquiryController::class, 'assign'])->name('enquiries.assign');
        Route::put('/enquiries/{enquiry}/status', [EnquiryController::class, 'update'])->name('enquiries.update');
        Route::post('/enquiries/{enquiry}/responses', [EnquiryController::class, 'respond'])->name('enquiries.respond');
        Route::get('/sourcing', [SourcingQueueController::class, 'index'])->name('sourcing.index');
        Route::get('/sourcing/matching', [SourcingQueueController::class, 'index'])->name('sourcing.matching')->defaults('queue', 'matching');
        Route::get('/sourcing/approvals', [SourcingQueueController::class, 'index'])->name('sourcing.approvals')->defaults('queue', 'approvals');
        Route::get('/sourcing/{sourcingRequest}', [SourcingQueueController::class, 'show'])->name('sourcing.show');
        Route::put('/sourcing/{sourcingRequest}/assignment', [SourcingQueueController::class, 'assign'])->name('sourcing.assign');
        Route::put('/sourcing/{sourcingRequest}/status', [SourcingQueueController::class, 'update'])->name('sourcing.update');
        Route::post('/sourcing/{sourcingRequest}/matches/{sourcingMatch}/approve', [SourcingQueueController::class, 'approve'])->name('sourcing.approve');
        Route::post('/sourcing/{sourcingRequest}/matches/{sourcingMatch}/reject', [SourcingQueueController::class, 'reject'])->name('sourcing.reject');
        Route::get('/visits', [SiteVisitController::class, 'index'])->name('visits.index');
        Route::put('/visits/{siteVisit}/assignment', [SiteVisitController::class, 'assign'])->name('visits.assign');
        Route::put('/visits/{siteVisit}', [SiteVisitController::class, 'update'])->name('visits.update');
        Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
        Route::put('/offers/{offer}', [OfferController::class, 'update'])->name('offers.update');
        Route::get('/documents', [DocumentAccessController::class, 'index'])->name('documents.index');
        Route::put('/documents/{documentAccessRequest}', [DocumentAccessController::class, 'update'])->name('documents.update');

        Route::middleware('permission:staff.verification')->group(function () {
            Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');
            Route::get('/verification/new-reviews', [VerificationQueueController::class, 'newReviews'])->name('verification.new');
            Route::get('/verification/missing-documents', [VerificationQueueController::class, 'missing'])->name('verification.missing');
            Route::get('/verification/exceptions', [VerificationQueueController::class, 'exceptions'])->name('verification.exceptions');
            Route::get('/verification/expiring-checks', [VerificationQueueController::class, 'expiring'])->name('verification.expiring');
            Route::get('/verification/completed-reviews', [VerificationQueueController::class, 'completed'])->name('verification.completed');
            Route::get('/verification/{property}/history', [VerificationQueueController::class, 'history'])->name('verification.history');
            Route::put('/verification/{property}/assignment', [VerificationQueueController::class, 'assign'])->name('verification.assign');
            Route::put('/verification/items/{verificationItem}/decision', [VerificationQueueController::class, 'decide'])->name('verification.decide');
            Route::put('/verification/items/{verificationItem}', [VerificationController::class, 'review'])->name('verification.review');
            Route::post('/verification/items/{verificationItem}/evidence', [VerificationController::class, 'evidence'])->name('verification.evidence');
            Route::post('/verification/files/{evidenceFile}/grants', [VerificationController::class, 'grant'])->name('verification.grant');
            Route::get('/verification/{property}', [VerificationController::class, 'show'])->name('verification.show');
            Route::put('/verification/{property}/override', [VerificationController::class, 'override'])->name('verification.override');
        });

        Route::post('/owners/{propertyOwner}/verification', [SellerDeskController::class, 'verifyOwner'])->name('owners.verify');
        Route::post('/properties/{property}/owners', [SellerDeskController::class, 'addOwner'])->name('properties.owners.store');
        Route::put('/authorities/{marketingAuthority}', [SellerDeskController::class, 'decideAuthority'])->name('authorities.update');
        Route::post('/properties/{property}/listing-approval', [SellerDeskController::class, 'approveListing'])->name('properties.listing');
        Route::put('/properties/{property}/availability', [SellerDeskController::class, 'applyAvailability'])->name('properties.availability');
        Route::post('/enquiries/{enquiry}/briefing', [SellerDeskController::class, 'briefing'])->name('enquiries.briefing');
        Route::post('/offers/{offer}/presentation', [SellerDeskController::class, 'presentOffer'])->name('offers.present');
        Route::post('/properties/{property}/transactions', [SellerDeskController::class, 'transaction'])->name('properties.transactions.store');

        Route::middleware('permission:staff.properties')->group(function () {
            Route::get('/media', [PropertyMediaController::class, 'library'])->name('media.index');
            Route::get('/properties', [PropertyInventoryController::class, 'index'])->name('properties.index');
            Route::get('/properties/create', [PropertyInventoryController::class, 'create'])->name('properties.create');
            Route::post('/properties', [PropertyInventoryController::class, 'store'])->name('properties.store');
            Route::get('/properties/{property}/edit', [PropertyInventoryController::class, 'edit'])->name('properties.edit');
            Route::put('/properties/{property}', [PropertyInventoryController::class, 'update'])->name('properties.update');
            Route::get('/properties/{property}', [PropertyInventoryController::class, 'show'])->name('properties.show');
            Route::put('/properties/{property}/status', [PropertyInventoryController::class, 'transition'])->name('properties.transition');
            Route::post('/properties/{property}/sell-portion', [PropertyInventoryController::class, 'sellPortion'])->name('properties.sell-portion');
            Route::get('/properties/{property}/media', [PropertyMediaController::class, 'edit'])->name('properties.media');
            Route::post('/properties/{property}/photos', [PropertyMediaController::class, 'storePhoto'])->name('properties.photos.store');
            Route::post('/properties/{property}/videos', [PropertyMediaController::class, 'storeVideo'])->name('properties.videos.store');
            Route::put('/properties/{property}/media/order', [PropertyMediaController::class, 'reorder'])->name('properties.media.order');
            Route::delete('/properties/{property}/media/{propertyMedia}', [PropertyMediaController::class, 'destroy'])->name('properties.media.destroy');
            Route::put('/properties/{property}/media/{propertyMedia}/cover', [PropertyMediaController::class, 'cover'])->name('properties.media.cover');
            Route::get('/properties/{property}/media/{propertyMedia}/original', [PropertyMediaController::class, 'original'])->name('properties.media.original');
            Route::get('/properties/{property}/media/{propertyMedia}/preview', [PropertyMediaController::class, 'preview'])->name('properties.media.preview');
            Route::put('/properties/{property}/details', [PropertyMediaController::class, 'updateDetails'])->name('properties.details.update');
        });

        Route::middleware('permission:staff.properties')->group(function () {
            Route::get('/listings', [ListingReviewController::class, 'index'])->name('listings.index');
            Route::get('/listings/{property}', [ListingReviewController::class, 'show'])->name('listings.show');
            Route::post('/listings/{property}/requests', [ListingReviewController::class, 'requestInformation'])->name('listings.request');
            Route::post('/listings/{property}/approval', [ListingReviewController::class, 'approve'])->name('listings.approve');
            Route::get('/listings/{property}/media/{propertyMedia}', [ListingReviewController::class, 'media'])->name('listings.media');
        });

        Route::middleware('permission:staff.brokerage')->group(function () {
            Route::get('/authorities', [SellerDeskController::class, 'authorities'])->name('authorities.index');
            Route::get('/applications', [CapabilityApplicationController::class, 'index'])->name('applications.index');
            Route::get('/applications/{application}', [CapabilityApplicationController::class, 'edit'])->name('applications.edit');
            Route::put('/applications/{application}', [CapabilityApplicationController::class, 'update'])->name('applications.update');
            Route::get('/referrals', [ReferralReviewController::class, 'index'])->name('referrals.index');
            Route::get('/referrals/{referral}', [ReferralReviewController::class, 'show'])->name('referrals.show');
            Route::put('/referrals/{referral}', [ReferralReviewController::class, 'update'])->name('referrals.update');
            Route::get('/referrals/{referral}/files/{kind}', [ReferralReviewController::class, 'file'])
                ->whereIn('kind', ['consent', 'evidence'])
                ->name('referrals.file');
        });
        Route::middleware('permission:staff.intelligence|intelligence.publish')->group(function () {
            Route::get('/projects', [DevelopmentProjectController::class, 'index'])->name('projects.index');
            Route::get('/projects/create', [DevelopmentProjectController::class, 'create'])->name('projects.create');
            Route::post('/projects', [DevelopmentProjectController::class, 'store'])->name('projects.store');
            Route::get('/projects/{project}', [DevelopmentProjectController::class, 'show'])->name('projects.show');
            Route::get('/projects/{project}/edit', [DevelopmentProjectController::class, 'edit'])->name('projects.edit');
            Route::put('/projects/{project}', [DevelopmentProjectController::class, 'update'])->name('projects.update');
            Route::post('/projects/{project}/submission', [DevelopmentProjectController::class, 'submit'])->name('projects.submit');
            Route::post('/projects/{project}/publication', [DevelopmentProjectController::class, 'publish'])->name('projects.publish');
            Route::post('/projects/{project}/milestones', [DevelopmentProjectController::class, 'milestone'])->name('projects.milestone');
            Route::post('/projects/{project}/evidence', [DevelopmentProjectController::class, 'evidence'])->name('projects.evidence');

            Route::get('/imagery', [ImageryController::class, 'index'])->name('imagery.index');
            Route::get('/imagery/scenes/create', [ImageryController::class, 'createScene'])->name('imagery.scenes.create');
            Route::post('/imagery/scenes', [ImageryController::class, 'storeScene'])->name('imagery.scenes.store');
            Route::get('/imagery/scenes/{scene}/edit', [ImageryController::class, 'editScene'])->name('imagery.scenes.edit');
            Route::put('/imagery/scenes/{scene}', [ImageryController::class, 'updateScene'])->name('imagery.scenes.update');
            Route::post('/imagery/scenes/{scene}/submission', [ImageryController::class, 'submitScene'])->name('imagery.scenes.submit');
            Route::post('/imagery/scenes/{scene}/publication', [ImageryController::class, 'publishScene'])->name('imagery.scenes.publish');
            Route::get('/imagery/comparisons/create', [ImageryController::class, 'createComparison'])->name('imagery.comparisons.create');
            Route::post('/imagery/comparisons', [ImageryController::class, 'storeComparison'])->name('imagery.comparisons.store');
            Route::get('/imagery/comparisons/{comparison}', [ImageryController::class, 'showComparison'])->name('imagery.comparisons.show');
            Route::post('/imagery/comparisons/{comparison}/submission', [ImageryController::class, 'submitComparison'])->name('imagery.comparisons.submit');
            Route::post('/imagery/comparisons/{comparison}/publication', [ImageryController::class, 'publishComparison'])->name('imagery.comparisons.publish');

            Route::get('/planning', [PlanningRecordController::class, 'index'])->name('planning.index');
            Route::get('/planning/create', [PlanningRecordController::class, 'create'])->name('planning.create');
            Route::post('/planning', [PlanningRecordController::class, 'store'])->name('planning.store');
            Route::get('/planning/{record}', [PlanningRecordController::class, 'show'])->name('planning.show');
            Route::put('/planning/{record}', [PlanningRecordController::class, 'update'])->name('planning.update');
            Route::post('/planning/{record}/submission', [PlanningRecordController::class, 'submit'])->name('planning.submit');
            Route::post('/planning/{record}/publication', [PlanningRecordController::class, 'publish'])->name('planning.publish');

            Route::get('/prices', [VerifiedSaleController::class, 'index'])->name('prices.index');
            Route::post('/prices/sales', [VerifiedSaleController::class, 'store'])->name('prices.sales.store');
            Route::post('/prices/sales/{sale}/submission', [VerifiedSaleController::class, 'submit'])->name('prices.sales.submit');
            Route::post('/prices/sales/{sale}/publication', [VerifiedSaleController::class, 'publish'])->name('prices.sales.publish');

            Route::get('/reports', [ResearchReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/create', [ResearchReportController::class, 'create'])->name('reports.create');
            Route::post('/reports', [ResearchReportController::class, 'store'])->name('reports.store');
            Route::get('/reports/{report}', [ResearchReportController::class, 'show'])->name('reports.show');
            Route::get('/reports/{report}/edit', [ResearchReportController::class, 'edit'])->name('reports.edit');
            Route::put('/reports/{report}', [ResearchReportController::class, 'update'])->name('reports.update');
            Route::post('/reports/{report}/submission', [ResearchReportController::class, 'submit'])->name('reports.submit');
            Route::post('/reports/{report}/publication', [ResearchReportController::class, 'publish'])->name('reports.publish');

            Route::get('/research/compliance', [ResearchCommitmentController::class, 'index'])->name('commitments.index');
            Route::post('/research/assignments/{assignment}/report', [ResearchCommitmentController::class, 'fulfillReport'])->name('commitments.report');
            Route::post('/research/assignments/{assignment}/imagery', [ResearchCommitmentController::class, 'fulfillImagery'])->name('commitments.imagery');
        });

        Route::middleware('permission:staff.sales')->group(function () {
            Route::get('/sales', [SalesPipelineController::class, 'index'])->name('sales.index');
            Route::get('/sales/completion', [SalesPipelineController::class, 'completion'])->name('sales.completion');
            Route::get('/sales/create', [SalesPipelineController::class, 'create'])->name('sales.create');
            Route::post('/sales', [SalesPipelineController::class, 'store'])->name('sales.store');
            Route::get('/sales/{salesTransaction}', [SalesPipelineController::class, 'show'])->name('sales.show');
            Route::put('/sales/{salesTransaction}', [SalesPipelineController::class, 'update'])->name('sales.update');
            Route::post('/sales/{salesTransaction}/offers', [SalesPipelineController::class, 'offer'])->name('sales.offers.store');
            Route::post('/sales/{salesTransaction}/milestones', [SalesPipelineController::class, 'milestone'])->name('sales.milestones.store');
            Route::post('/sales/{salesTransaction}/documents', [SalesPipelineController::class, 'document'])->name('sales.documents.store');
            Route::post('/sales/{salesTransaction}/closing', [ClosingController::class, 'review'])->name('sales.closing');
            Route::post('/sales/{salesTransaction}/evidence', [ClosingController::class, 'evidence'])->name('sales.evidence');
            Route::post('/sales/{salesTransaction}/completion', [ClosingController::class, 'complete'])->name('sales.complete');
            Route::post('/sales/{salesTransaction}/commission', [CommissionAgreementController::class, 'store'])->name('commissions.store');
        });

        Route::get('/commissions', [CommissionAgreementController::class, 'index'])->name('commissions.index');
        Route::get('/commissions/{commissionAgreement}', [CommissionAgreementController::class, 'show'])->name('commissions.show');
        Route::post('/commissions/{commissionAgreement}/acceptance', [CommissionAgreementController::class, 'accept'])->name('commissions.accept');
        Route::post('/commissions/{commissionAgreement}/revision', [CommissionAgreementController::class, 'revise'])->name('commissions.revise');
        Route::post('/commissions/{commissionAgreement}/dispute', [CommissionAgreementController::class, 'dispute'])->name('commissions.dispute');

        Route::middleware('permission:staff.finance')->group(function () {
            Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
            Route::post('/settlements/{commissionAgreement}/approval', [SettlementController::class, 'approve'])->name('settlements.approve');
            Route::post('/settlements/{commissionAgreement}/payment', [SettlementController::class, 'initiate'])->name('settlements.initiate');
            Route::post('/settlements/{commissionAgreement}/paid', [SettlementController::class, 'pay'])->name('settlements.pay');
        });

        Route::middleware('permission:staff.finance')->group(function () {
            Route::get('/memberships', [MembershipController::class, 'index'])->name('memberships.index');
            Route::post('/memberships/{subscription}/activation', [MembershipController::class, 'activate'])->name('memberships.activate');
            Route::post('/memberships/{subscription}/renewal', [MembershipController::class, 'renew'])->name('memberships.renew');
            Route::post('/memberships/{subscription}/cancellation', [MembershipController::class, 'cancel'])->name('memberships.cancel');
            Route::post('/memberships/{subscription}/credit', [MembershipController::class, 'credit'])->name('memberships.credit');
        });

        Route::middleware('permission:staff.communications')->group(function () {
            Route::get('/messages/templates', [CommunicationsController::class, 'templates'])->name('messages.templates');
            Route::put('/messages/templates/{messageTemplate}', [CommunicationsController::class, 'updateTemplate'])->name('messages.templates.update');
            Route::get('/messages/deliveries', [CommunicationsController::class, 'deliveries'])->name('messages.deliveries');
        });

        Route::get('/notifications', [WorkspaceNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{notification}', [WorkspaceNotificationController::class, 'open'])->name('notifications.open');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
