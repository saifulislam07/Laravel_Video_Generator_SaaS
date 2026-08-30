<?php

use App\Http\Controllers\BillingCallbackController;
use App\Http\Controllers\ShotstackWebhookController;
use App\Http\Controllers\SocialConnectController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::post('logout', function (App\Livewire\Actions\Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::post('webhooks/shotstack', ShotstackWebhookController::class)->name('webhooks.shotstack');

Route::match(['get', 'post'], 'billing/callback/{gateway}', BillingCallbackController::class)
    ->name('billing.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');

    Volt::route('assets', 'assets.gallery')->name('assets.gallery');

    Volt::route('projects', 'projects.index')->name('projects.index');
    Volt::route('projects/{project}/build', 'projects.builder')->name('projects.builder');
    Volt::route('projects/{project}/timeline', 'projects.timeline')->name('projects.timeline');

    Volt::route('pricing', 'billing.pricing')->name('billing.pricing');
    Volt::route('billing', 'billing.history')->name('billing.history');
    Volt::route('billing/checkout/{order}', 'billing.mock-gateway')->name('billing.mock');

    Volt::route('social', 'social.accounts')->name('social.index');
    Route::get('social/facebook/connect', [SocialConnectController::class, 'connect'])->name('social.connect');
    Route::get('social/facebook/callback', [SocialConnectController::class, 'callback'])->name('social.callback');
});

Route::middleware(['auth', 'verified', 'role:'.\App\Models\User::ROLE_ADMIN])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Volt::route('/', 'admin.dashboard')->name('dashboard');
        Volt::route('users', 'admin.users')->name('users');
        Volt::route('characters', 'admin.characters')->name('characters');
        Volt::route('renders', 'admin.renders')->name('renders');
    });

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
