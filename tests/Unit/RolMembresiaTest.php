<?php

use App\Enums\RolUsuario;
use App\Models\User;
use App\Models\UserRole;

/**
 * Construye un User con su relación de roles precargada, sin tocar la BD,
 * para probar la lógica pura de membresía y sus invariantes.
 */
function usuarioConRoles(RolUsuario ...$roles): User
{
    $user = new User;

    $user->setRelation('roles', collect($roles)->map(
        fn (RolUsuario $rol) => new UserRole(['rol' => $rol->value])
    ));

    return $user;
}

test('tieneRol detecta los roles asignados a la cuenta', function () {
    $user = usuarioConRoles(RolUsuario::DEPOSITANTE, RolUsuario::PRESTAMISTA);

    expect($user->tieneRol(RolUsuario::DEPOSITANTE))->toBeTrue();
    expect($user->tieneRol(RolUsuario::PRESTAMISTA))->toBeTrue();
    expect($user->tieneRol(RolUsuario::CURADOR))->toBeFalse();
});

test('tieneAlgunRol es verdadero si posee al menos uno de los roles', function () {
    $user = usuarioConRoles(RolUsuario::DEPOSITANTE);

    expect($user->tieneAlgunRol(RolUsuario::CURADOR, RolUsuario::DEPOSITANTE))->toBeTrue();
    expect($user->tieneAlgunRol(RolUsuario::CURADOR, RolUsuario::PRESTAMISTA))->toBeFalse();
});

test('los helpers de rol reflejan la membresía', function () {
    $user = usuarioConRoles(RolUsuario::DEPOSITANTE, RolUsuario::PRESTAMISTA);

    expect($user->esDepositante())->toBeTrue();
    expect($user->esPrestamista())->toBeTrue();
    expect($user->esCurador())->toBeFalse();
});

test('administracion es un rol interno reconocido por los helpers', function () {
    $user = usuarioConRoles(RolUsuario::ADMIN);

    expect($user->esAdministrador())->toBeTrue();
    expect($user->esCurador())->toBeTrue();
    expect($user->esUsuarioInterno())->toBeTrue();
    expect(RolUsuario::rolesInternos())->toContain(RolUsuario::ADMIN);
});

test('la membresía de curador puede coexistir con perfiles de autoservicio', function () {
    $user = usuarioConRoles(RolUsuario::PRESTAMISTA, RolUsuario::CURADOR);
    expect($user->esCurador())->toBeTrue()->and($user->esPrestamista())->toBeTrue()->and($user->esAdministrador())->toBeFalse();
});

test('el ayudante tiene membresía curatorial sin recibir administración', function () {
    $user = usuarioConRoles(RolUsuario::AYUDANTE_CURADOR);
    expect($user->esCurador())->toBeTrue()->and($user->esUsuarioInterno())->toBeTrue()->and($user->esAdministrador())->toBeFalse();
});
