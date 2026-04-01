<?php

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\InventarioGestionColeccionController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('inventariogestioncoleccion', InventarioGestionColeccionController::class)->names('inventariogestioncoleccion');
});
