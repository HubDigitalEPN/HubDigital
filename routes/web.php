<?php

use App\Livewire\ActivarRol;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/portal')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/roles/activar/{rol}', ActivarRol::class)->name('roles.activar');
});

require __DIR__.'/settings.php';
