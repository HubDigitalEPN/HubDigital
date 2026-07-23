<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Domain\Events\DevolucionRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaSolicitada;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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

it('no falla cuando no hay curadores registrados', function () {
    event(new ProrrogaSolicitada(PrestamoId::generate(), 'investigador-1', new DateTimeImmutable));

    expect(DB::table('notifications')->count())->toBe(0);
});
