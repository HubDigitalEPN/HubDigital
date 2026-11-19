<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\EliminarEspecimenes;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;

uses(Tests\DatabaseFeatureTestCase::class);

function especimenParaBorrar(string $estado = 'disponible'): string {
    $id = (string) Str::uuid();
    DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => 'QA-BORRAR-'.$id, 'estado' => $estado, 'localidad' => '', 'colector' => 'PRUEBA QA']);
    return $id;
}

test('el borrado definitivo admite publicado y en prestamo sin borrar el prestamo ni otros registros', function (): void {
    $usuario = User::factory()->curador()->create(); $this->actingAs($usuario);
    $id = especimenParaBorrar('en_prestamo'); $otro = especimenParaBorrar();
    $solicitud = (string) Str::uuid(); $item = (string) Str::uuid();
    DB::table('prestamos.solicitudes_prestamo')->insert(['id' => $solicitud, 'codigo' => substr(str_replace('-', '', $solicitud),0,10), 'investigador_id' => $usuario->id, 'estado' => 'Borrador']);
    DB::table('prestamos.items_prestamo')->insert(['id' => $item, 'solicitud_prestamo_id' => $solicitud, 'especimen_id' => $id, 'especimen_codigo_externo' => 'CODIGO HISTORICO QA', 'cantidad_solicitada' => 1, 'especimen_snapshot' => json_encode(['codigo' => 'CODIGO HISTORICO QA'])]);
    DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true]);
    DB::table('taxonomia.valores_columnas_especimen')->insert(['especimen_id' => $id, 'campo' => 'extra_qa', 'valor' => 'Dato de prueba']);
    $revision = DB::table('divulgacion.portal_cache_revision')->value('version');
    expect(app(EliminarEspecimenes::class)->eliminar([$id]))->toBe([$id])
        ->and(DB::table('taxonomia.especimenes')->where('id', $id)->exists())->toBeFalse()
        ->and(DB::table('taxonomia.especimenes')->where('id', $otro)->exists())->toBeTrue()
        ->and(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->exists())->toBeFalse()
        ->and(DB::table('taxonomia.valores_columnas_especimen')->where('especimen_id', $id)->exists())->toBeFalse()
        ->and(DB::table('prestamos.items_prestamo')->where('id', $item)->value('especimen_id'))->toBeNull()
        ->and(DB::table('prestamos.items_prestamo')->where('id', $item)->value('especimen_codigo_externo'))->toBe('CODIGO HISTORICO QA')
        ->and(DB::table('prestamos.solicitudes_prestamo')->where('id', $solicitud)->exists())->toBeTrue()
        ->and(DB::table('divulgacion.portal_cache_revision')->value('version'))->toBeGreaterThan($revision);
    expect(DB::table('taxonomia.eliminaciones_especimenes')->where('especimen_id',$id)->value('eliminado_por'))->toBe($usuario->id);
    expect(app(EliminarEspecimenes::class)->eliminar([$id]))->toBe([])
        ->and(DB::table('taxonomia.eliminaciones_especimenes')->where('especimen_id',$id)->count())->toBe(1);
});

test('abrir o cancelar la confirmacion no borra y confirmar elimina solo los UUID seleccionados', function (): void {
    $usuario = User::factory()->curador()->create();
    $uno = especimenParaBorrar(); $dos = especimenParaBorrar(); $otro = especimenParaBorrar();
    $panel = Livewire::actingAs($usuario)->withQueryParams(['q'=>'QA-BORRAR-'])->test(EspecimenIndex::class)
        ->call('abrirBorrado', [$uno,$dos])->assertSet('borradoAbierto',true);
    expect($panel->get('borradoIds'))->toEqualCanonicalizing([$uno,$dos]);
    expect(DB::table('taxonomia.especimenes')->whereIn('id',[$uno,$dos])->count())->toBe(2);
    $panel->set('borradoAbierto',false)->call('confirmarBorrado');
    expect(DB::table('taxonomia.especimenes')->whereIn('id',[$uno,$dos])->count())->toBe(2);
    $panel->call('abrirBorrado',[$uno,$dos])->call('confirmarBorrado')->assertSet('borradoAbierto',false)->assertDispatched('especimenes-eliminados');
    expect(DB::table('taxonomia.especimenes')->whereIn('id',[$uno,$dos])->count())->toBe(0)
        ->and(DB::table('taxonomia.especimenes')->where('id',$otro)->exists())->toBeTrue();
});

test('el servidor aplica el permiso de borrar aun sin pasar por el boton', function (string $rol): void {
    $usuario = User::factory()->create(['rol'=>$rol]);$this->actingAs($usuario);
    $id = especimenParaBorrar();
    expect(fn()=>app(EliminarEspecimenes::class)->eliminar([$id]))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(DB::table('taxonomia.especimenes')->where('id',$id)->exists())->toBeTrue();
})->with(['DEPOSITANTE','PASANTE','AYUDANTE_CURADOR']);

test('un fallo de relacion revierte el lote completo y su auditoria', function (): void {
    $this->actingAs(User::factory()->curador()->create());
    $uno = especimenParaBorrar();$dos = especimenParaBorrar();
    DB::statement('CREATE TABLE public.qa_borrado_referencia (especimen_id uuid REFERENCES taxonomia.especimenes(id))');
    try {
        DB::table('public.qa_borrado_referencia')->insert(['especimen_id'=>$dos]);
        expect(fn()=>app(EliminarEspecimenes::class)->eliminar([$uno,$dos]))->toThrow(\Illuminate\Database\QueryException::class);
        expect(DB::table('taxonomia.especimenes')->whereIn('id',[$uno,$dos])->count())->toBe(2)
            ->and(DB::table('taxonomia.eliminaciones_especimenes')->whereIn('especimen_id',[$uno,$dos])->count())->toBe(0);
    } finally { DB::statement('DROP TABLE public.qa_borrado_referencia'); }
});
