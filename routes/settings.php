<?php

use App\Http\Controllers\Auth\InitialPasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/initial-password', [InitialPasswordController::class, 'show'])->name('password.initial.edit');
    Route::post('settings/initial-password', [InitialPasswordController::class, 'update'])->name('password.initial.update');

    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')->name('security.edit');
});
