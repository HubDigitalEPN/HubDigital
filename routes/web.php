<?php

use App\Http\Controllers\AdminBootstrapController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RedirigirTablaCatalogo;
use App\Http\Controllers\VersionPortalController;
use App\Livewire\ActivarRol;
use App\Livewire\Administracion\ContenidosTextos;
use App\Livewire\Administracion\ConfiguracionSistema;
use App\Livewire\Administracion\ConfiguracionExpediente;
use App\Livewire\Administracion\GestionUsuarios;
use App\Livewire\Administracion\TablasCatalogos;
use App\Livewire\Administracion\TablasCatalogosDatos;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/archivo/imagen/{id}', \App\Http\Controllers\ServirImagenArchivo::class)->middleware(['auth', 'verified'])->name('archivo.imagen');

Route::view('/', 'portal-inicio')->name('home');
Route::get('/contenido/fotos/{foto}/{hash}', \App\Http\Controllers\FotoEditorialPortal::class)
    ->where('foto', 'inicio|depositos|logo')->where('hash', '[a-f0-9]{64}')->name('portal.foto-editorial');
Route::view('/el-laboratorio', 'portal-laboratorio')->name('laboratorio');
Route::get('/el-laboratorio/fotos/{foto}/{hash}', \App\Http\Controllers\FotoPaginaLaboratorio::class)
    ->where('foto', '(?:'.implode('|', array_keys(config('pagina-laboratorio.fotos'))).'|bloque-[a-f0-9]{16})')->where('hash', '[a-f0-9]{64}')->name('laboratorio.foto');
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
    Route::get('/administracion/usuarios', GestionUsuarios::class)
        ->middleware('role:admin')
        ->name('admin.usuarios');
    Route::get('/administracion/botones', \App\Livewire\Administracion\GestionBotones::class)
        ->middleware('role:admin')->name('admin.botones');
    Route::get('/administracion/menu-lateral', \App\Livewire\Administracion\GestionMenuLateral::class)
        ->middleware('role:admin')->name('admin.menu-lateral');
    Route::get('/administracion/configuracion', ConfiguracionSistema::class)
        ->middleware('role:admin')
        ->name('admin.configuracion');
    Route::get('/administracion/siglas-expedientes', ConfiguracionExpediente::class)
        ->middleware('role:admin')
        ->name('admin.siglas-expedientes');
    Route::get('/administracion/contenidos', ContenidosTextos::class)
        ->middleware('role:curador')->name('admin.contenidos');
    Route::get('/administracion/formato-ipt', RedirigirTablaCatalogo::class)
        ->defaults('tablaCatalogo', 'formato-ipt')->defaults('destinoCatalogo', 'admin.matrices')
        ->middleware('role:admin')->name('admin.formato-ipt');
    Route::get('/administracion/contenidos/fichas/{taxon}/foto', \Modules\CatalogoPublico\Presentation\Http\Controllers\FotoFichaTaxonomica::class)
        ->whereUuid('taxon')->middleware('role:curador')->name('admin.ficha-foto');
    Route::get('/administracion/catalogos', TablasCatalogosDatos::class)
        ->middleware('role:curador')->name('admin.catalogos');
    Route::get('/administracion/matrices', TablasCatalogos::class)
        ->middleware('role:curador')->name('admin.matrices');
    foreach (['instituciones', 'cargos', 'grupos-animales', 'fuentes-revocacion'] as $catalogo) {
        Route::get('/administracion/'.$catalogo, RedirigirTablaCatalogo::class)
            ->defaults('tablaCatalogo', $catalogo)->middleware('role:curador')->name('admin.'.$catalogo);
    }
    Route::get('/administracion/localidades', RedirigirTablaCatalogo::class)
        ->defaults('tablaCatalogo', 'localidades-ecuador')->middleware('role:admin')->name('admin.localidades');
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

Route::get('/inventario/captura-dwc', \App\Livewire\CapturaDarwinCore::class)
    ->middleware(['auth', 'verified', 'role:pasante,curador'])->name('inventario.captura-dwc');
