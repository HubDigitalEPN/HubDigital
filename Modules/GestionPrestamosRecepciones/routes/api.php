<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\CargarDocumentacionOficialController;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\CompletarDatosManualesController;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\DeterminarDocumentacionRequeridaController;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\RegistrarSolicitudDepositoController;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\SolicitarIntervencionCuratoriaController;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ValidarDocumentacionInicialController;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ValidarIdentidadSolicitudController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    Route::post('solicitudes-deposito',
        RegistrarSolicitudDepositoController::class)->name('solicitudes-deposito.store');

    Route::get('solicitudes-deposito/{id}/documentacion-requerida',
        DeterminarDocumentacionRequeridaController::class)->name('solicitudes-deposito.documentacion-requerida');

    Route::post('solicitudes-deposito/{id}/intervencion-curatoria',
        SolicitarIntervencionCuratoriaController::class)->name('solicitudes-deposito.intervencion-curatoria');

    Route::post('solicitudes-deposito/{id}/validacion-documentacion',
        ValidarDocumentacionInicialController::class)->name('solicitudes-deposito.validacion-documentacion');

    Route::post('solicitudes-deposito/{id}/documentacion-oficial',
        CargarDocumentacionOficialController::class)->name('solicitudes-deposito.documentacion-oficial');

    Route::patch('solicitudes-deposito/{id}/datos-faltantes',
        CompletarDatosManualesController::class)->name('solicitudes-deposito.datos-faltantes');

    Route::post('solicitudes-deposito/{id}/validacion-identidad',
        ValidarIdentidadSolicitudController::class)->name('solicitudes-deposito.validacion-identidad');
});

// Las rutas 'solicitudes-prestamo' apuntaban a SolicitudPrestamoController, una clase
// que no existe: solo podían devolver 500 y además rompían `php artisan route:list`.
// El flujo de préstamos se atiende hoy por Livewire (routes/web.php). Si en el futuro
// se expone por API, hay que escribir el controlador y registrarlas bajo auth:sanctum
// como el resto del grupo v1.
