<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\GestionPrestamosRecepcionesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('gestionprestamosrecepciones', GestionPrestamosRecepcionesController::class)->names('gestionprestamosrecepciones');
});
