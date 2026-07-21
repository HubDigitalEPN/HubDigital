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

test('asignarRol impide combinar CURADOR con roles auto-servicio', function () {
    $user = usuarioConRoles(RolUsuario::PRESTAMISTA);

    $user->asignarRol(RolUsuario::CURADOR);
})->throws(DomainException::class);

test('asignarRol impide que una cuenta curador asuma otros roles', function () {
    $user = usuarioConRoles(RolUsuario::CURADOR);

    $user->asignarRol(RolUsuario::DEPOSITANTE);
})->throws(DomainException::class);
