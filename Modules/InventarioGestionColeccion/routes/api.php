<?php

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Http\Controllers\InventarioGestionColeccionController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('inventariogestioncoleccions', InventarioGestionColeccionController::class)->names('inventariogestioncoleccion');
});
