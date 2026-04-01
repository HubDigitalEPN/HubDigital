<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionPrestamosRecepciones\Http\Controllers\GestionPrestamosRecepcionesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('gestionprestamosrecepciones', GestionPrestamosRecepcionesController::class)->names('gestionprestamosrecepciones');
});
