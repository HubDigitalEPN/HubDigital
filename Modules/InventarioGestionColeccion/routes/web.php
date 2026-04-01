<?php

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\InventarioGestionColeccionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('inventariogestioncoleccion', InventarioGestionColeccionController::class)->names('inventariogestioncoleccion');
});
