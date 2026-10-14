<?php

use App\Http\Controllers\Account\CapabilityApplicationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PhoneLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Land\PropertyController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\LandDirectoryController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'private.cache'])->group(function () {
    Route::post('/land/{property}/saved', [PropertyController::class, 'save'])->name('land.saved.store');
    Route::delete('/land/{property}/saved', [PropertyController::class, 'unsave'])->name('land.saved.destroy');
    Route::get('/land/{property}/enquire', [PropertyController::class, 'createEnquiry'])->name('land.enquiries.create');
    Route::post('/land/{property}/enquiries', [PropertyController::class, 'storeEnquiry'])
        ->middleware('throttle:enquiries')
        ->name('land.enquiries.store');
});

Route::get('/land/item/{property}', [LandDirectoryController::class, 'showItem'])->name('land.item');
Route::get('/land/{county}/{locality}/{property}', [LandDirectoryController::class, 'show'])->name('land.show');
Route::get('/land/{county?}/{locality?}', [LandDirectoryController::class, 'index'])->name('land.index');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::post('/login/phone', [PhoneLoginController::class, 'store'])->name('login.phone');
    Route::post('/login/phone/verify', [PhoneLoginController::class, 'verify'])->name('login.phone.verify');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware(['auth', 'private.cache'])
    ->name('logout');

Route::middleware(['auth', 'private.cache'])->group(function () {
    Route::get('/account/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspace/{workspace}', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::get('/account/applications', [CapabilityApplicationController::class, 'index'])->name('account.applications.index');
    Route::get('/account/applications/create', [CapabilityApplicationController::class, 'create'])->name('account.applications.create');
    Route::post('/account/applications', [CapabilityApplicationController::class, 'store'])->name('account.applications.store');
    Route::post('/account/phone/confirm', [ProfileController::class, 'confirmPhone'])->name('account.phone.confirm');
    Route::get('/account/email/verify/{id}/{hash}', [ProfileController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('account.email.verify');
});
