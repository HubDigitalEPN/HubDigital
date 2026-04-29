<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Login;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', Login::class)->name('login');

    Route::post('logout', function () {
        request()->session()->forget('admin_authenticated');

        return redirect()->route('admin.login');
    })->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware('admin.auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
});
