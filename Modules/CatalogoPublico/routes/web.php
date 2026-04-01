<?php

use Illuminate\Support\Facades\Route;
use Modules\CatalogoPublico\Http\Controllers\CatalogoPublicoController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('catalogopublicos', CatalogoPublicoController::class)->names('catalogopublico');
});
