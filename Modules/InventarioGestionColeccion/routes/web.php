<?php

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Http\Controllers\InventarioGestionColeccionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('inventariogestioncoleccions', InventarioGestionColeccionController::class)->names('inventariogestioncoleccion');
});
