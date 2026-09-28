<?php

use App\Http\Controllers\AdminBootstrapController;
use App\Http\Controllers\PushSubscriptionController;
use App\Livewire\ActivarRol;
use App\Livewire\Administracion\CentroAdministracion;
use App\Livewire\Administracion\ConfiguracionSistema;
use App\Livewire\Administracion\ConfiguracionExpediente;
use App\Livewire\Administracion\ConfiguracionTextosWizard;
use App\Livewire\Administracion\InstitucionesCatalogo;
use App\Livewire\Administracion\LocalidadesEcuadorCatalogo;
use App\Livewire\Administracion\GruposAnimalesCatalogo;
use App\Livewire\Administracion\FuentesRevocacionFirma;
use App\Livewire\Administracion\GestionUsuarios;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/archivo/imagen/{id}', \App\Http\Controllers\ServirImagenArchivo::class)->middleware(['auth', 'verified'])->name('archivo.imagen');

Route::view('/', 'portal-inicio')->name('home');

Route::middleware(['guest', 'throttle:10,1'])->group(function (): void {
    Route::get('/instalacion/administrador', [AdminBootstrapController::class, 'create'])
        ->name('admin.bootstrap.create');
    Route::post('/instalacion/administrador', [AdminBootstrapController::class, 'store'])
        ->name('admin.bootstrap.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/roles/activar/{rol}', ActivarRol::class)->name('roles.activar');
    Route::get('/administracion', CentroAdministracion::class)
        ->middleware('role:admin')
        ->name('admin.centro');
    Route::get('/administracion/usuarios', GestionUsuarios::class)
        ->middleware('role:admin')
        ->name('admin.usuarios');
    Route::get('/administracion/configuracion', ConfiguracionSistema::class)
        ->middleware('role:admin')
        ->name('admin.configuracion');
    Route::get('/administracion/siglas-expedientes', ConfiguracionExpediente::class)
        ->middleware('role:admin')
        ->name('admin.siglas-expedientes');
    Route::get('/administracion/textos-depositos', ConfiguracionTextosWizard::class)
        ->middleware('role:admin')
        ->name('admin.textos-depositos');
    Route::get('/administracion/instituciones', InstitucionesCatalogo::class)
        ->name('admin.instituciones');
    Route::get('/administracion/localidades', LocalidadesEcuadorCatalogo::class)
        ->middleware('role:admin')->name('admin.localidades');
    Route::get('/administracion/grupos-animales', GruposAnimalesCatalogo::class)
        ->name('admin.grupos-animales');
    Route::get('/administracion/fuentes-revocacion', FuentesRevocacionFirma::class)
        ->name('admin.fuentes-revocacion');
    Route::prefix('pwa')->name('pwa.')->group(function (): void {
        Route::get('/configuracion', [PushSubscriptionController::class, 'configuration'])
            ->name('configuration');
        Route::post('/suscripciones', [PushSubscriptionController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('subscriptions.store');
        Route::delete('/suscripciones', [PushSubscriptionController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('subscriptions.destroy');
    });
});

require __DIR__.'/settings.php';
