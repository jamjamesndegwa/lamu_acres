<?php

use App\Http\Controllers\Admin\AdminReportExportController;
use App\Http\Controllers\Admin\ApprovalsController;
use App\Http\Controllers\Admin\GeographyController;
use App\Http\Controllers\Admin\InfrastructureController;
use App\Http\Controllers\Admin\ManualEmailController;
use App\Http\Controllers\Admin\MapLayerController;
use App\Http\Controllers\Admin\OversightController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\SystemOperationsController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\Communications\ExternalCommunicationsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceDashboardController;
use App\Http\Controllers\WorkspaceNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'private.cache', 'workspace:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');

        Route::middleware('permission:admin.approvals')->group(function () {
            Route::get('/approvals', [ApprovalsController::class, 'index'])->name('approvals.index');
        });

        Route::middleware('permission:admin.oversight')->group(function () {
            Route::get('/oversight/sales', OversightController::class)->name('oversight.sales');
            Route::get('/oversight/inventory', OversightController::class)->name('oversight.inventory');
            Route::get('/oversight/buyers', OversightController::class)->name('oversight.buyers');
            Route::get('/oversight/brokers', OversightController::class)->name('oversight.brokers');
            Route::get('/oversight/subscriptions', OversightController::class)->name('oversight.subscriptions');
        });
        Route::get('/notifications', [WorkspaceNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [WorkspaceNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('/notifications/{notification}', [WorkspaceNotificationController::class, 'open'])->name('notifications.open');
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
            Route::get('/geography/layers', [MapLayerController::class, 'index'])->name('geography.layers.index');
            Route::get('/geography/layers/create', [MapLayerController::class, 'create'])->name('geography.layers.create');
            Route::post('/geography/layers', [MapLayerController::class, 'store'])->name('geography.layers.store');
            Route::get('/geography/layers/{layer}/edit', [MapLayerController::class, 'edit'])->name('geography.layers.edit');
            Route::put('/geography/layers/{layer}', [MapLayerController::class, 'update'])->name('geography.layers.update');
            Route::get('/geography/infrastructure', [InfrastructureController::class, 'index'])->name('geography.infrastructure.index');
            Route::get('/geography/infrastructure/create', [InfrastructureController::class, 'create'])->name('geography.infrastructure.create');
            Route::post('/geography/infrastructure', [InfrastructureController::class, 'store'])->name('geography.infrastructure.store');
            Route::get('/geography/infrastructure/{feature}/edit', [InfrastructureController::class, 'edit'])->name('geography.infrastructure.edit');
            Route::put('/geography/infrastructure/{feature}', [InfrastructureController::class, 'update'])->name('geography.infrastructure.update');
        });

        Route::middleware('permission:admin.commercial')->group(function () {
            Route::get('/plans', [SubscriptionPlanController::class, 'edit'])->name('plans.edit');
            Route::put('/plans', [SubscriptionPlanController::class, 'update'])->name('plans.update');
            Route::get('/commercial/commissions', [SubscriptionPlanController::class, 'commissions'])->name('commercial.commissions');
            Route::get('/commercial/listings', [SubscriptionPlanController::class, 'listings'])->name('commercial.listings');
            Route::get('/commercial/verification', [SubscriptionPlanController::class, 'verification'])->name('commercial.verification');
        });

        Route::middleware('permission:admin.reports')->group(function () {
            Route::get('/reports/export/{dataset}', AdminReportExportController::class)->name('reports.export');
            Route::get('/reports/sales', ReportController::class)->name('reports.sales');
            Route::get('/reports/commissions', ReportController::class)->name('reports.commissions');
            Route::get('/reports/financial', ReportController::class)->name('reports.financial');
            Route::get('/reports/audit', ReportController::class)->name('reports.audit');
        });

        Route::middleware('permission:admin.settings')->group(function () {
            Route::get('/operations', [SystemOperationsController::class, 'index'])->name('operations.index');
        });

        Route::middleware('role_or_permission:communications.email.view|communications.sms.view')->group(function () {
            Route::get('/communications/external', [ExternalCommunicationsController::class, 'index'])->name('communications.external.index');
            Route::get('/communications/external/{messageDelivery}', [ExternalCommunicationsController::class, 'show'])->name('communications.external.show');
            Route::middleware('permission:communications.email.view')->group(function () {
                Route::get('/communications/manual-email', [ManualEmailController::class, 'create'])->name('communications.manual-email.create');
                Route::post('/communications/manual-email', [ManualEmailController::class, 'store'])->name('communications.manual-email.store');
            });
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
