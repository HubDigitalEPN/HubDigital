<?php

use Illuminate\Support\Facades\Route;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ConsultarInformacionDivulgadaController;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ModificarConfiguracionDivulgacionController;
use Modules\CatalogoPublico\Presentation\Http\Controllers\SincronizarEspecimenesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    Route::post(
        'especimenes-divulgables/sincronizaciones',
        SincronizarEspecimenesController::class
    )->name('especimenes-divulgables.sincronizar');

    Route::patch(
        'especimenes-divulgables/configuracion',
        ModificarConfiguracionDivulgacionController::class
    )->name('especimenes-divulgables.configuracion');

    Route::get(
        'especimenes-divulgables/{occurrenceID}',
        ConsultarInformacionDivulgadaController::class
    )->name('especimenes-divulgables.show');
});
