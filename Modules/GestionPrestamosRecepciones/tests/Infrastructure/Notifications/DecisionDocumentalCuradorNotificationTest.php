<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\DecisionDocumentalCuradorNotification;
use Tests\TestCase;

uses(TestCase::class);

function notifiableCurador(): object
{
    return new class
    {
        public ?string $name = 'Dra. Ana Pérez';
    };
}

it('describe una aprobación sin motivo', function () {
    $notificacion = new DecisionDocumentalCuradorNotification(
        solicitudId: '11111111-1111-1111-1111-111111111111',
        numero: 'MEPN-INV-00007',
        tipoTramite: 'Depósito',
        decision: 'aprobada',
        nombreCuradorDecide: 'Dr. Luis Mora',
    );

    $data = $notificacion->toArray(notifiableCurador());

    expect($data['tipo'])->toBe('solicitud_aprobada_por_curador')
        ->and($data['mensaje'])->toContain('Dr. Luis Mora')
        ->and($data['mensaje'])->toContain('aprobó')
        ->and($data['mensaje'])->not->toContain('Motivo')
        ->and($data['icono'])->toBe('check-circle');
});

it('incluye el motivo en un rechazo', function () {
    $notificacion = new DecisionDocumentalCuradorNotification(
        solicitudId: '22222222-2222-2222-2222-222222222222',
        numero: 'MEPN-INV-00008',
        tipoTramite: 'Donación',
        decision: 'rechazada',
        motivo: 'Falta el permiso de movilización',
        nombreCuradorDecide: 'Dr. Luis Mora',
    );

    $data = $notificacion->toArray(notifiableCurador());

    expect($data['tipo'])->toBe('solicitud_rechazada_por_curador')
        ->and($data['mensaje'])->toContain('rechazó')
        ->and($data['mensaje'])->toContain('Motivo: Falta el permiso de movilización')
        ->and($data['icono'])->toBe('x-circle');
});

it('genera un correo con asunto acorde a la decisión', function () {
    $notificacion = new DecisionDocumentalCuradorNotification(
        solicitudId: '33333333-3333-3333-3333-333333333333',
        numero: 'MEPN-INV-00009',
        tipoTramite: 'Depósito',
        decision: 'rechazada',
        motivo: 'Documentación incompleta',
        nombreCuradorDecide: 'Dr. Luis Mora',
    );

    $mail = $notificacion->toMail(notifiableCurador());

    expect($mail->subject)->toContain('rechazada')
        ->and(collect($mail->introLines)->implode(' '))->toContain('Documentación incompleta');
});
