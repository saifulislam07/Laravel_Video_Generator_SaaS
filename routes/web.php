<?php

use App\Http\Controllers\ShotstackWebhookController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::post('webhooks/shotstack', ShotstackWebhookController::class)->name('webhooks.shotstack');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');

    Volt::route('assets', 'assets.gallery')->name('assets.gallery');

    Volt::route('projects', 'projects.index')->name('projects.index');
    Volt::route('projects/{project}/build', 'projects.builder')->name('projects.builder');
    Volt::route('projects/{project}/timeline', 'projects.timeline')->name('projects.timeline');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
