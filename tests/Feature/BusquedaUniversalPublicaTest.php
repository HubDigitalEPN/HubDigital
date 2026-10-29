<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EnlaceSeleccionCatalogo;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\SeleccionPaginaChat;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\BusquedaUniversalPublica;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

uses(\Tests\DatabaseFeatureTestCase::class);

test('la búsqueda universal combina texto fechas y números y reconoce el linaje público', function (): void {
    $f = qa7ColeccionFixture();
    $servicio = app(BusquedaUniversalPublica::class);
    $base = FiltrosBusqueda::desde(['filtroTaxonId' => $f['taxon']]);
    foreach (['QA7 1994 -77.765', 'QA7 10/01/1994', 'Qaheptidae Colectora'] as $texto) {
        $opciones = $servicio->opciones($base, $texto);
        expect(array_column($opciones['filas'], 'id'))->toEqualCanonicalizing($f['ids']);
    }
    expect($servicio->opciones($base, 'QA7 2020')['filas'])->toBe([])
        ->and($servicio->opciones($base, '%_ OR 1=1')['filas'])->toBe([]);
});

test('buscar y contar nunca revela campos ocultos ni registros no publicados', function (): void {
    $f = qa7ColeccionFixture();
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['specimen_notes' => 'NotaPrivadaUniversal']);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['ids'])->update(['specimen_notes_visible' => false]);
    $base = FiltrosBusqueda::desde(['filtroTaxonId' => $f['taxon']]);
    $servicio = app(BusquedaUniversalPublica::class);
    expect($servicio->opciones($base, 'NotaPrivadaUniversal')['filas'])->toBe([]);
    // La publicación vigente se deriva del filo confirmado mediante trigger.
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['taxon_id' => null]);
    expect(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->value('publicado'))->toBeFalse();
    expect(array_column($servicio->opciones(FiltrosBusqueda::vacio(), 'QA7')['filas'], 'id'))->not->toContain($f['ids'][0]);
});

test('el LOV conserva el filtro en mapa árbol tarjetas registros y enlaces compartidos', function (): void {
    $f = qa7ColeccionFixture();
    $filtros = FiltrosBusqueda::desde(['filtroUniversal' => 'QA7 1994', 'filtroRegistroId' => $f['ids'][0]]);
    $parametros = EnlaceSeleccionCatalogo::parametros($filtros, '', '');
    $seleccion = SeleccionPaginaChat::desde($parametros);
    expect($seleccion->filtros->universal)->toBe('QA7 1994')
        ->and($seleccion->filtros->registroId)->toBe($f['ids'][0])
        ->and(app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($seleccion->filtros)->pluck('te.id')->all())->toBe([$f['ids'][0]]);
    $puntos = app(PortalEstadisticas::class)->puntosParaMapa(['universal' => 'QA7 1994', 'registro_id' => $f['ids'][0]]);
    expect(array_sum(array_column($puntos, 'total')))->toBe(1);
    $componente = Livewire::test(PortalCatalogo::class)->call('aplicarUniversal', 'QA7 1994', $f['ids'][0])
        ->assertHasNoErrors()->assertSet('filtroRegistroId', $f['ids'][0])->assertSet('filtroUniversal', 'QA7 1994');
    foreach (['arbol', 'tarjetas', 'registros', 'mapa'] as $vista) {
        $componente->set('vista', $vista)->assertHasNoErrors()->assertSet('filtroRegistroId', $f['ids'][0]);
    }
});

test('la descripción institucional reemplaza solo su registro y refresca su caché', function (): void {
    DB::table('usuarios.textos_portal')->updateOrInsert(['clave' => 'inicio.hero_descripcion'], ['contenido' => 'Texto anterior demasiado largo']);
    \App\Support\PortalCopy::forget();
    expect(\App\Support\PortalCopy::text('inicio.hero_descripcion'))->toBe('Texto anterior demasiado largo');
    $this->artisan('portal:descripcion-institucional')->assertSuccessful();
    $texto = 'El Laboratorio de Invertebrados de la Escuela Politécnica Nacional conserva, estudia y conecta con la sociedad el patrimonio biológico que custodia el Museo de Historia Natural Gustavo Orcés V.';
    expect(DB::table('usuarios.textos_portal')->where('clave', 'inicio.hero_descripcion')->value('contenido'))->toBe($texto)
        ->and(\App\Support\PortalCopy::text('inicio.hero_descripcion'))->toBe($texto);
});

test('la ayuda del chat orienta al LOV para localidad y combinación de criterios', function (): void {
    $ayuda = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AyudaContextualPortal::class)
        ->responder('¿Cómo filtrar por provincia y método?', []);
    expect($ayuda['texto'])->toContain('Búsqueda universal', 'pulsa la lupa')->not->toContain('Ejemplar y colecta');
    $portal = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal::class);
    $respuesta = $portal->responder('Buscar por localidad', app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler::class));
    expect($respuesta['texto'])->toContain('Búsqueda universal', 'lupa');
});
