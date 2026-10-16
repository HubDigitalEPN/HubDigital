<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxones\ListarTaxonesHandler;

uses(Tests\DatabaseFeatureTestCase::class);

test('curaduría puede listar taxones y localidades con UUID v5 sin perder sus relaciones padre', function (): void {
    $taxonPadre = substr_replace((string) Str::uuid(), '5', 14, 1);
    $taxonHijo = substr_replace((string) Str::uuid(), '5', 14, 1);
    $localidadPadre = substr_replace((string) Str::uuid(), '5', 14, 1);
    $localidadHija = substr_replace((string) Str::uuid(), '5', 14, 1);
    $prefijo = '000-UUID5-'.Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $taxonPadre, 'nombre_cientifico' => $prefijo.'-familia', 'rango' => 'familia', 'estado' => 'activo', 'padre_id' => null],
        ['id' => $taxonHijo, 'nombre_cientifico' => $prefijo.'-genero', 'rango' => 'genero', 'estado' => 'activo', 'padre_id' => $taxonPadre],
    ]);
    DB::table('taxonomia.localidades')->insert([
        ['id' => $localidadPadre, 'nombre_canonico' => $prefijo.'-provincia', 'rango' => 'provincia', 'padre_id' => null],
        ['id' => $localidadHija, 'nombre_canonico' => $prefijo.'-sitio', 'rango' => 'sitio', 'padre_id' => $localidadPadre],
    ]);
    $this->actingAs(User::factory()->curador()->create());
    $taxones = collect(app(ListarTaxonesHandler::class)->handle()->items);
    expect($taxones->firstWhere('id', $taxonHijo)->padreId)->toBe($taxonPadre)
        ->and($taxones->firstWhere('id', $taxonHijo)->padreNombre)->toBe($prefijo.'-familia');
    $this->get('/inventario/taxonomia/taxones')->assertOk()
        ->assertDontSeeText('TaxonId inválido');
    $this->get('/inventario/taxonomia/localidades')->assertOk()
        ->assertSeeText($prefijo.'-provincia')->assertSeeText($prefijo.'-sitio')
        ->assertDontSeeText('LocalidadId inválido');
});
