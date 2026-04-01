<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\GestionPrestamosRecepcionesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('gestionprestamosrecepciones', GestionPrestamosRecepcionesController::class)->names('gestionprestamosrecepciones');
});
