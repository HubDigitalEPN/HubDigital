<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\User;
use Modules\GestionPrestamosRecepciones\Domain\Events\DevolucionRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaSolicitada;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

it('avisa a los curadores en la campana cuando se solicita una prórroga', function () {
    $curador = User::factory()->create(['rol' => RolUsuario::CURADOR]);
    $prestamoId = PrestamoId::generate();

    event(new ProrrogaSolicitada($prestamoId, 'investigador-1', new DateTimeImmutable));

    $notificacion = $curador->unreadNotifications()->sole();

    expect($notificacion->data['tipo'])->toBe('prestamo_prorroga_solicitada')
        ->and($notificacion->data['url'])->toBe(
            route('prestamos.curador.prestamo.gestionar-prorroga', (string) $prestamoId),
        )
        ->and($notificacion->data['mensaje'])->toContain('prórroga');
});

it('usa la ruta de cierre cuando se registra una devolución', function () {
    $curador = User::factory()->create(['rol' => RolUsuario::CURADOR]);
    $investigador = User::factory()->create(['rol' => RolUsuario::PRESTAMISTA]);
    $prestamoId = PrestamoId::generate();

    event(new DevolucionRegistrada($prestamoId, (string) $investigador->id, new DateTimeImmutable));

    $notificacion = $curador->unreadNotifications()->sole();

    expect($notificacion->data['tipo'])->toBe('prestamo_devolucion_registrada')
        ->and($notificacion->data['url'])->toBe(
            route('prestamos.curador.prestamo.cerrar', (string) $prestamoId),
        );
});

it('avisa al administrador porque posee facultades curatoriales', function () {
    $administrador = User::factory()->administrador()->create();
    $prestamoId = PrestamoId::generate();

    event(new ProrrogaSolicitada($prestamoId, 'investigador-1', new DateTimeImmutable));

    $notificacion = $administrador->unreadNotifications()->sole();

    expect($administrador->esCurador())->toBeTrue()
        ->and($notificacion->data['tipo'])->toBe('prestamo_prorroga_solicitada');
});

it('no envía el aviso curatorial a un depositante', function () {
    $depositante = User::factory()->depositante()->create();
    event(new ProrrogaSolicitada(PrestamoId::generate(), 'investigador-1', new DateTimeImmutable));

    expect($depositante->notifications()->count())->toBe(0);
});
