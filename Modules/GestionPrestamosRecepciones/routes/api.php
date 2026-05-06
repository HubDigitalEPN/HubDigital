<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\SolicitudPrestamoController;

Route::prefix('v1')->group(function () {
    Route::post('solicitudes-prestamo', [SolicitudPrestamoController::class, 'store'])
        ->name('solicitudes-prestamo.store');

    Route::put('solicitudes-prestamo/{id}', [SolicitudPrestamoController::class, 'update'])
        ->name('solicitudes-prestamo.update');

    Route::post('solicitudes-prestamo/{id}/envios', [SolicitudPrestamoController::class, 'enviar'])
        ->name('solicitudes-prestamo.enviar');

    Route::post('solicitudes-prestamo/{id}/actas-firmadas', [SolicitudPrestamoController::class, 'subirActa'])
        ->name('solicitudes-prestamo.subir-acta');
});
