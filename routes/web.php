<?php

use App\Http\Controllers\AdminBootstrapController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\VersionPortalController;
use App\Livewire\ActivarRol;
use App\Livewire\Administracion\CentroAdministracion;
use App\Livewire\Administracion\ConfiguracionSistema;
use App\Livewire\Administracion\ConfiguracionExpediente;
use App\Livewire\Administracion\ConfiguracionTextosWizard;
use App\Livewire\Administracion\InstitucionesCatalogo;
use App\Livewire\Administracion\CargosCatalogo;
use App\Livewire\Administracion\LocalidadesEcuadorCatalogo;
use App\Livewire\Administracion\GruposAnimalesCatalogo;
use App\Livewire\Administracion\FuentesRevocacionFirma;
use App\Livewire\Administracion\GestionUsuarios;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/archivo/imagen/{id}', \App\Http\Controllers\ServirImagenArchivo::class)->middleware(['auth', 'verified'])->name('archivo.imagen');

Route::view('/', 'portal-inicio')->name('home');
Route::get('/version', VersionPortalController::class)->name('portal.version');
Route::view('/informacion/privacidad', 'portal-informacion-legal', ['tipo' => 'privacidad'])->name('portal.privacidad');
Route::view('/informacion/terminos', 'portal-informacion-legal', ['tipo' => 'terminos'])->name('portal.terminos');
foreach (['licencia' => 'Licencia', 'citar' => 'Cómo citar', 'creditos' => 'Créditos'] as $seccion => $titulo) {
    Route::view('/informacion/'.$seccion, 'portal-informacion-plataforma', compact('seccion', 'titulo'))->name('portal.'.$seccion);
}

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
    Route::get('/administracion/textos-sistema', \App\Livewire\Administracion\ConfiguracionTextosSistema::class)
        ->middleware('role:admin')->name('admin.textos-sistema');
    Route::get('/administracion/textos-portal', \App\Livewire\Administracion\ConfiguracionTextosPortal::class)
        ->middleware('role:curador')->name('admin.textos-portal');
    Route::get('/administracion/fichas-taxonomicas', \App\Livewire\Administracion\FichasTaxonomicasPortal::class)
        ->middleware('role:curador')->name('admin.fichas-taxonomicas');
    Route::get('/administracion/fichas-taxonomicas/{taxon}/foto', \Modules\CatalogoPublico\Presentation\Http\Controllers\FotoFichaTaxonomica::class)
        ->whereUuid('taxon')->middleware('role:curador')->name('admin.ficha-foto');
    Route::get('/administracion/instituciones', InstitucionesCatalogo::class)
        ->middleware('role:curador')
        ->name('admin.instituciones');
    Route::get('/administracion/cargos', CargosCatalogo::class)
        ->middleware('role:curador')->name('admin.cargos');
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
