<?php

use App\Enums\RolUsuario;
use App\Models\PerfilPersonalizado;
use App\Support\AccesoCatalogo;
use App\Support\MenuLateral;
use Illuminate\Support\Facades\DB;
use Tests\InfrastructureTestCase;

uses(InfrastructureTestCase::class);

test('la asignación de una pantalla de catálogo concede acceso al perfil elegible', function (): void {
    $menu = app(MenuLateral::class);
    $configuraciones = collect();
    DB::shouldReceive('table->orderBy->orderBy->pluck')->andReturnUsing(fn () => $configuraciones);

    $asistente = new PerfilPersonalizado(['codigo' => 'PERFIL_00000000000000000000000000', 'nombre' => 'Asistente de Colecciones']);
    $destinos = $menu->destinos(collect([...RolUsuario::cases(), $asistente]));
    foreach ([AccesoCatalogo::CONSULTA, AccesoCatalogo::IMPORTACION] as $ruta) {
        expect($destinos[$ruta]['rolesPermitidos'])->toContain(RolUsuario::PASANTE->value)
            ->toContain($asistente->value)
            ->toContain(RolUsuario::DEPOSITANTE->value)
            ->and($menu->accesoDelegado($ruta, RolUsuario::PASANTE))->toBeFalse();
    }

    $opcion = [
        'texto' => 'Consultar catálogo',
        'ruta' => AccesoCatalogo::CONSULTA,
        'grupo' => 'catalogo',
        'roles' => [RolUsuario::PASANTE->value, RolUsuario::DEPOSITANTE->value],
        'permisos' => [RolUsuario::PASANTE->value => ['editar']],
        'visible' => true,
    ];
    $configuraciones->put('especimenes', json_encode($opcion, JSON_THROW_ON_ERROR));

    expect($menu->accesoDelegado(AccesoCatalogo::CONSULTA, RolUsuario::PASANTE))->toBeTrue()
        ->and($menu->accesoDelegado(AccesoCatalogo::CONSULTA, RolUsuario::PASANTE, 'editar'))->toBeTrue()
        ->and($menu->accesoDelegado(AccesoCatalogo::CONSULTA, RolUsuario::PASANTE, 'crear'))->toBeFalse()
        ->and($menu->accesoDelegado(AccesoCatalogo::CONSULTA, RolUsuario::PASANTE, 'eliminar'))->toBeFalse()
        ->and($menu->accesoDelegado(AccesoCatalogo::IMPORTACION, RolUsuario::PASANTE))->toBeFalse()
        ->and($menu->accesoDelegado(AccesoCatalogo::CONSULTA, RolUsuario::DEPOSITANTE))->toBeTrue()
        ->and($menu->visiblePara(RolUsuario::PASANTE)['catalogo']['hijos'])->toHaveKey('especimenes');

    $opcion['visible'] = false;
    $configuraciones->put('especimenes', json_encode($opcion, JSON_THROW_ON_ERROR));
    expect($menu->accesoDelegado(AccesoCatalogo::CONSULTA, RolUsuario::PASANTE))->toBeTrue()
        ->and($menu->visiblePara(RolUsuario::PASANTE)['catalogo']['hijos'])->not->toHaveKey('especimenes');

    $opcion['roles'] = [RolUsuario::CURADOR->value];
    $configuraciones->put('especimenes', json_encode($opcion, JSON_THROW_ON_ERROR));
    expect($menu->accesoDelegado(AccesoCatalogo::CONSULTA, RolUsuario::PASANTE))->toBeFalse();

    $importacion = $opcion;
    $importacion['ruta'] = AccesoCatalogo::IMPORTACION;
    $importacion['roles'] = [$asistente->value];
    $importacion['permisos'] = [$asistente->value => ['importar']];
    $importacion['visible'] = true;
    $configuraciones->put('importar-catalogo', json_encode($importacion, JSON_THROW_ON_ERROR));
    expect($menu->accesoDelegado(AccesoCatalogo::IMPORTACION, $asistente, 'importar'))->toBeTrue()
        ->and($menu->accesoDelegado(AccesoCatalogo::CONSULTA, $asistente))->toBeFalse()
        ->and($menu->visiblePara($asistente))->toHaveKeys(['dashboard', 'catalogo'])
        ->and($menu->visiblePara($asistente)['catalogo']['hijos'])->toHaveKey('importar-catalogo');

    foreach ($destinos as $ruta => $destino) {
        expect($destino['rolesPermitidos'])->toContain($asistente->value, RolUsuario::PASANTE->value, RolUsuario::DEPOSITANTE->value);
        expect($menu->accesoDelegado($ruta, $asistente, 'funcion-inexistente'))->toBeFalse();
    }

    $solicitudes = [...$opcion, 'ruta' => 'prestamos.curador.solicitudes', 'grupo' => 'prestamos-equipo',
        'roles' => [$asistente->value], 'permisos' => [$asistente->value => ['revisar']], 'visible' => true];
    $configuraciones->put('solicitudes', json_encode($solicitudes, JSON_THROW_ON_ERROR));
    expect($menu->accesoDelegado('prestamos.curador.solicitudes', $asistente, 'revisar'))->toBeTrue()
        ->and($menu->accesoDelegado('admin.bootstrap.store', $asistente))->toBeFalse()
        ->and($menu->visiblePara($asistente)['prestamos-equipo']['hijos'])->toHaveKey('solicitudes');
});
