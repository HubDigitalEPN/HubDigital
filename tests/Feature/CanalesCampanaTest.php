<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications as Avisos;
use NotificationChannels\WebPush\WebPushChannel;

uses(\Tests\InfrastructureTestCase::class);

function avisosCampana(): array
{
    $id = '00000000-0000-0000-0000-000000000001';
    return [
        new Avisos\ActaRecepcionFirmadaNotification($id, 'DEP-001'),
        new Avisos\AvisoPrestamoCuradorNotification('prestamo', 'Aviso de prueba', 'https://example.test/tramite', 'bell'),
        new Avisos\CodigoQrDisponibleNotification($id, 'DEP-001', 'QR-001', 'Depósito'),
        new Avisos\CorreccionCuratorialNotification($id, 'DEP-001', []),
        new Avisos\DecisionDocumentalCuradorNotification($id, 'DEP-001', 'Depósito', 'rechazada', 'MOTIVO PRIVADO'),
        new Avisos\EntregaFisicaAnunciadaNotification($id, 'DEP-001', null),
        new Avisos\LoteRecibidoParaActaNotification($id, 'DEP-001', 'Depósito', 'Receptor', false),
        new Avisos\NuevaSolicitudPorRevisarNotification($id, 'DEP-001', 'Depósito'),
        new Avisos\OrdenAccionCorrectivaNotification($id, 'DEP-001', 'MOTIVO PRIVADO', 'Corregir'),
        new Avisos\RecepcionConObservacionesNotification($id, 'DEP-001', ['MOTIVO PRIVADO']),
        new Avisos\RecepcionFinalizadaNotification($id, 'DEP-001', 'recibido'),
        new Avisos\SolicitudRechazadaNotification($id, 'DEP-001', 'MOTIVO PRIVADO'),
    ];
}

function destinatarioCampana(bool $suscrito = true): object
{
    return new class($suscrito) {
        use \Illuminate\Notifications\Notifiable;
        public string $name = 'Persona QA';
        public string $email = 'qa@example.test';
        public function __construct(private bool $suscrito) {}
        public function getKey(): string { return 'qa'; }
        public function pushSubscriptions(): object { return new class($this->suscrito) {
            public function __construct(private bool $suscrito) {}
            public function exists(): bool { return $this->suscrito; }
        }; }
    };
}

beforeEach(function (): void {
    config(['webpush.vapid.subject' => 'mailto:qa@example.test', 'webpush.vapid.public_key' => 'publica-ficticia',
        'webpush.vapid.private_key' => 'privada-ficticia']);
});

test('todos los eventos de campana incluyen correo y push con mensajes discretos sin acciones', function (): void {
    $usuario = destinatarioCampana();
    foreach (avisosCampana() as $aviso) {
        expect($aviso)->toBeInstanceOf(ShouldQueue::class);
        expect($aviso->via($usuario))->toContain('database', 'mail', WebPushChannel::class)
            ->and($aviso->afterCommit)->toBeTrue();
        $push = $aviso->toWebPush($usuario)->toArray();
        expect($push['title'])->toStartWith('HubDigital · ')
            ->and($push['body'])->not->toContain('MOTIVO PRIVADO')
            ->and($push['actions'] ?? [])->toBe([])
            ->and($push['data']['url'])->toBe($aviso->toArray($usuario)['url'])
            ->and($aviso->toMail($usuario))->toBeInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class);
        expect($aviso->via(destinatarioCampana(false)))->toContain('mail', 'database')->not->toContain(WebPushChannel::class);
    }
});

test('un evento crea un trabajo independiente por canal y soloCorreo no duplica campana ni push', function (): void {
    Queue::fake();
    $aviso = new Avisos\NuevaSolicitudPorRevisarNotification('00000000-0000-0000-0000-000000000001', 'DEP-001', 'Depósito');
    $usuario = destinatarioCampana();
    $usuario->notify($aviso);
    Queue::assertPushed(SendQueuedNotifications::class, 3);
    foreach (['database', 'mail', WebPushChannel::class] as $canal) {
        Queue::assertPushed(SendQueuedNotifications::class, fn ($trabajo) => $trabajo->channels === [$canal] && $trabajo->afterCommit === true);
    }
    expect($aviso->soloCorreo()->via($usuario))->toBe(['mail']);
    expect($aviso->canalesExternos()->via($usuario))->toContain('mail', WebPushChannel::class)->not->toContain('database')
        ->and($aviso->canalesExternos()->via(destinatarioCampana(false)))->toBe(['mail']);
});
