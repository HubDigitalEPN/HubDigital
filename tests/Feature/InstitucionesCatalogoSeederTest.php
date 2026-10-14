<?php

declare(strict_types=1);

use Database\Seeders\InstitucionesCatalogoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

test('la institución del depositante bootstrap está disponible sin inventar un código CES', function (): void {
    DB::table('usuarios.instituciones_catalogo')->where('nombre', 'Kintiflow')->delete();
    (new InstitucionesCatalogoSeeder)->run();
    (new InstitucionesCatalogoSeeder)->run();
    $filas = DB::table('usuarios.instituciones_catalogo')->where('nombre', 'Kintiflow')->get();
    expect($filas)->toHaveCount(1)->and((bool) $filas->first()->activo)->toBeTrue()
        ->and($filas->first()->codigo_ces)->toBeNull();
});

test('el catálogo no reactiva la institución deshabilitada por el administrador', function (): void {
    (new InstitucionesCatalogoSeeder)->run();
    DB::table('usuarios.instituciones_catalogo')->where('nombre', 'Kintiflow')->update(['activo' => false]);
    (new InstitucionesCatalogoSeeder)->run();
    expect((bool) DB::table('usuarios.instituciones_catalogo')->where('nombre', 'Kintiflow')->value('activo'))->toBeFalse();
});
