<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\Services\ColumnasRegistroPublico;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ConfiguracionColumnasIndex;

uses(Tests\DatabaseFeatureTestCase::class);

it('permite al curador definir columnas públicas y restablecer todas sin publicar campos internos', function () {
    $curador = User::factory()->curador()->create();
    $panel = Livewire::actingAs($curador)->test(ConfiguracionColumnasIndex::class)
        ->set('columnasPublicas', ['occurrence_id', 'scientific_name', 'decimal_latitude'])
        ->call('guardarColumnasPublicas')->assertHasNoErrors();
    expect(array_column(app(ColumnasRegistroPublico::class)->visibles(), 'clave'))
        ->toBe(['occurrence_id', 'scientific_name', 'decimal_latitude']);
    $panel->call('mostrarTodasPublicas')->assertHasNoErrors();
    $claves = array_column(app(ColumnasRegistroPublico::class)->visibles(), 'clave');
    expect($claves)->toHaveCount(24)->toContain('disposition')->not->toContain('estado_revision', 'motivo_revision', 'archivo_r2');
});

it('rechaza columnas públicas desconocidas conservando la configuración anterior', function () {
    $curador = User::factory()->curador()->create();
    $antes = app(ColumnasRegistroPublico::class)->visibles();
    Livewire::actingAs($curador)->test(ConfiguracionColumnasIndex::class)
        ->set('columnasPublicas', ['occurrence_id', 'motivo_revision'])
        ->call('guardarColumnasPublicas')->assertHasErrors(['columnasPublicas']);
    expect(app(ColumnasRegistroPublico::class)->visibles())->toBe($antes);
});

it('impide que visitantes y usuarios externos administren las columnas del portal', function () {
    Livewire::test(ConfiguracionColumnasIndex::class)->assertForbidden();
    Livewire::actingAs(User::factory()->depositante()->create())
        ->test(ConfiguracionColumnasIndex::class)->assertForbidden();
});
