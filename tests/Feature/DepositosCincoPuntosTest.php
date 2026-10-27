<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Livewire\Dashboard;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\NotificacionCuratoriaAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\NotificacionInvestigadorAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\ActaRecepcionFirmadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\CodigoQrDisponibleNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\CorreccionCuratorialNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\DecisionDocumentalCuradorNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\LoteRecibidoParaActaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\NuevaSolicitudPorRevisarNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\OrdenAccionCorrectivaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\RecepcionConObservacionesNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\RecepcionFinalizadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\SolicitudRechazadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use NotificationChannels\WebPush\WebPushChannel;

beforeAll(function (): void {
    // PostgreSQL aislado ya fue migrado explícitamente. Evita que RefreshDatabase
    // ejecute migrate:fresh y mezcle las migraciones raíz con las modulares.
    if (getenv('DB_CONNECTION') === 'pgsql') {
        RefreshDatabaseState::$migrated = true;
    }
});

afterEach(function (): void {
    Date::setTestNow();
});

function solicitudDashboardCincoPuntos(
    User $propietario,
    string $numero,
    string $estado,
    string $creadaUtc,
    string $tipo = 'Depósito',
    ?string $aprobadaUtc = null,
): SolicitudDepositoEloquentModel {
    $solicitud = SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => $numero,
        'investigador_id' => (string) $propietario->id,
        'tipo_tramite' => $tipo,
        'estado' => $estado,
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
        'nro_lotes' => 1,
        'nro_individuos' => 2,
        'nro_morfoespecies' => 1,
    ]);
    $solicitud->timestamps = false;
    $solicitud->forceFill([
        'created_at' => CarbonImmutable::parse($creadaUtc),
        'updated_at' => CarbonImmutable::parse($creadaUtc),
        'aprobada_en' => $aprobadaUtc ? CarbonImmutable::parse($aprobadaUtc) : null,
    ])->saveQuietly();

    return $solicitud;
}

test('el dashboard usa meses civiles de Ecuador y el CSV coincide con el conjunto independiente', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2099-09-15T12:00:00Z'));
    $curador = User::factory()->curador()->create();
    $propietario = User::factory()->depositante()->create();

    $agosto = solicitudDashboardCincoPuntos($propietario, 'QA-DASH-AGO', EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value, '2099-09-01T04:30:00Z');
    $septiembre = solicitudDashboardCincoPuntos($propietario, 'QA-DASH-SEP', EstadoSolicitudDeposito::AprobadaDocumentalmente->value, '2099-09-01T05:30:00Z', 'Donación', '2099-09-03T05:30:00Z');
    solicitudDashboardCincoPuntos($propietario, 'QA-DASH-ABR', EstadoSolicitudDeposito::RequiereCorreccion->value, '2099-04-01T05:00:00Z');
    solicitudDashboardCincoPuntos($propietario, 'QA-DASH-FUERA', EstadoSolicitudDeposito::Rechazada->value, '2099-04-01T04:59:59Z');
    solicitudDashboardCincoPuntos($propietario, 'QA-DASH-BORRADOR', EstadoSolicitudDeposito::EnBorrador->value, '2099-09-10T12:00:00Z');
    solicitudDashboardCincoPuntos($propietario, 'QA-DASH-OCT98', EstadoSolicitudDeposito::Rechazada->value, '2098-10-01T05:00:00Z');
    solicitudDashboardCincoPuntos($propietario, 'QA-DASH-SEP98', EstadoSolicitudDeposito::Rechazada->value, '2098-10-01T04:59:59Z');
    solicitudDashboardCincoPuntos($propietario, 'QA-DASH-OCT97', EstadoSolicitudDeposito::Rechazada->value, '2097-10-01T05:00:00Z');
    solicitudDashboardCincoPuntos($propietario, 'QA-DASH-SEP97', EstadoSolicitudDeposito::Rechazada->value, '2097-10-01T04:59:59Z');

    RecepcionLoteEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'solicitud_deposito_id' => (string) $septiembre->id,
        'codigo_qr' => 'LOTE-QADASH',
        'tipo_tramite' => 'Donación',
        'estado' => 'Verificado con Observaciones',
        'items_verificacion' => [],
        'observaciones' => ['Envase exterior con una marca controlada.'],
        'verificado_en' => CarbonImmutable::parse('2099-09-05T15:00:00Z'),
    ]);

    $dashboard = new Dashboard;
    $dashboard->periodoAnalisis = '6';
    $grafico = Closure::bind(fn (): array => $this->graficoDepositosPorMes(), $dashboard, Dashboard::class)();
    $analitica = Closure::bind(fn (): array => $this->analiticaDepositos(), $dashboard, Dashboard::class)();
    $porEtiqueta = collect($grafico)->pluck('valor', 'etiqueta');

    expect($porEtiqueta['abr 99'])->toBe(1)
        ->and($porEtiqueta['ago 99'])->toBe(1)
        ->and($porEtiqueta['sep 99'])->toBe(1)
        ->and($analitica['indicadoresDepositos']['total'])->toBe(3)
        ->and($analitica['indicadoresDepositos']['aprobadas'])->toBe(1)
        ->and($analitica['indicadoresDepositos']['porRevisar'])->toBe(1)
        ->and($analitica['indicadoresDepositos']['porCorregir'])->toBe(1)
        ->and($analitica['indicadoresDepositos']['constatadas'])->toBe(1)
        ->and($analitica['indicadoresDepositos']['observaciones'])->toBe(1)
        ->and($analitica['indicadoresDepositos']['actasPendientes'])->toBe(1);

    $dashboard->periodoAnalisis = '12';
    $inicio12 = Closure::bind(fn (): CarbonImmutable => $this->inicioAnalisis(), $dashboard, Dashboard::class)();
    $grafico12 = collect(Closure::bind(fn (): array => $this->graficoDepositosPorMes(), $dashboard, Dashboard::class)())
        ->pluck('valor', 'etiqueta');
    $analitica12 = Closure::bind(fn (): array => $this->analiticaDepositos(), $dashboard, Dashboard::class)();
    expect($inicio12->toISOString())->toBe('2098-10-01T05:00:00.000000Z')
        ->and($grafico12['oct 98'])->toBe(1)
        ->and($analitica12['indicadoresDepositos']['total'])->toBe(5);

    $dashboard->periodoAnalisis = '24';
    $grafico24 = collect(Closure::bind(fn (): array => $this->graficoDepositosPorMes(), $dashboard, Dashboard::class)())
        ->pluck('valor', 'etiqueta');
    $analitica24 = Closure::bind(fn (): array => $this->analiticaDepositos(), $dashboard, Dashboard::class)();
    expect($grafico24['oct 97'])->toBe(1)
        ->and($grafico24['sep 98'])->toBe(1)
        ->and($analitica24['indicadoresDepositos']['total'])->toBe(7);

    $dashboard->periodoAnalisis = '6';

    $this->actingAs($curador);
    session(['rol_activo' => RolUsuario::CURADOR->value]);
    $respuesta = $dashboard->descargarReporteDepositos();
    ob_start();
    ($respuesta->getCallback())();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain('QA-DASH-AGO', 'QA-DASH-SEP', 'QA-DASH-ABR')
        ->not->toContain('QA-DASH-FUERA')
        ->not->toContain('QA-DASH-BORRADOR')
        ->and(substr_count($csv, "\n"))->toBe(4)
        ->and($csv)->toContain('2099-08-31 23:30')
        ->and($csv)->toContain('2099-09-01 00:30');

    expect($agosto->created_at?->toISOString())->toBe('2099-09-01T04:30:00.000000Z');
});

test('el acta distingue depósito y donación y conserva versión y observaciones sin los textos retirados', function (): void {
    $base = [
        'numeroSolicitud' => 'MEPN-INV-DEP-QA-ACTA',
        'tipoTramite' => 'Depósito',
        'nroPermisoRecoleccion' => 'MAATE-QA-2026',
        'nroPermisoMovilizacion' => 'MOV-QA-2026',
        'grupoAnimal' => 'Insecta',
        'nroIndividuos' => '120',
        'nroMorfoespecies' => '12',
        'nroLotes' => '8',
        'localidad' => str_repeat('Reserva científica de prueba con descripción extensa, ', 8),
        'estadoRecepcion' => 'Verificado con Observaciones',
        'observaciones' => [
            str_repeat('Observación sintética extensa para comprobar salto de línea y conservación del contenido. ', 12),
            'Dos recipientes requieren seguimiento curatorial.',
        ],
        'verificadoEn' => CarbonImmutable::parse('2026-09-11T15:00:00Z'),
    ];
    $datos = [
        'depositante' => (object) ['cargo' => 'Investigador de prueba', 'institucion' => 'Institución sintética'],
        'investigador' => 'Depositante Sintético',
        'curador' => 'Curador Sintético',
        'receptor' => 'Receptor Sintético',
        'fecha' => '11 de Septiembre de 2026',
        'versionActa' => 3,
        'perfilFirma' => [
            'rol' => 'curador',
            'bloque' => 'https://firmas.hubdigital.invalid/bloques/acta-recepcion/curador/v1',
            'zona' => 'https://firmas.hubdigital.invalid/zonas/acta-recepcion/curador/v1',
        ],
    ];

    $htmlDeposito = view('gestionprestamosrecepciones::pdf.acta-recepcion', [
        ...$datos,
        'recepcion' => (object) $base,
    ])->render();
    $htmlDonacion = view('gestionprestamosrecepciones::pdf.acta-recepcion', [
        ...$datos,
        'recepcion' => (object) [...$base, 'tipoTramite' => 'Donación'],
    ])->render();

    expect($htmlDeposito)->toContain('Modalidad:', 'Versión del original:', 'Firma electrónica del Curador responsable')
        ->not->toContain('Estado de recepción:', 'Resultado de la constatación física:', 'La trazabilidad de esta actuación se conserva en HubDigital.')
        ->and($htmlDeposito)->toContain('depositado temporalmente')
        ->and($htmlDeposito)->toContain('Dos recipientes requieren seguimiento curatorial.')
        ->and($htmlDonacion)->toContain('calidad de donación', 'transferencia definitiva')
        ->and($htmlDonacion)->not->toContain('depositado temporalmente');

    $pdf = Pdf::loadHTML($htmlDonacion)->output();
    expect($pdf)->toStartWith('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(10_000);
});

test('la notificación del portal queda disponible y SMTP se entrega por la cola existente', function (): void {
    Queue::fake();
    config()->set('hubdigital.qa_deposit_id', '');
    $depositante = User::factory()->depositante()->create();
    $curador = User::factory()->curador()->create();
    $solicitud = solicitudDashboardCincoPuntos($depositante, 'QA-COLA-CORREO', EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value, '2026-10-14 12:00:00');

    app(NotificacionCuratoriaAdapter::class)->notificarNuevaSolicitudPorRevisar((string) $solicitud->id);

    expect($curador->notifications()->where('type', NuevaSolicitudPorRevisarNotification::class)->count())->toBe(1);
    Queue::assertPushed(\Illuminate\Notifications\SendQueuedNotifications::class,
        fn ($trabajo) => $trabajo->channels === ['mail'] && $trabajo->queue === 'default'
            && $trabajo->notification->solicitudId === (string) $solicitud->id
            && $trabajo->notifiables->contains(fn ($destinatario) => $destinatario->id === $curador->id));
});

test('el destino de correo QA se limita al expediente local expresamente configurado', function (string $ambiente, bool $mismoExpediente, bool $redirige): void {
    Notification::fake();
    app()->instance('env', $ambiente);
    $depositante = User::factory()->depositante()->create();
    $curador = User::factory()->curador()->create();
    $solicitud = solicitudDashboardCincoPuntos($depositante, 'QA-CORREO-001', EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value, '2026-10-14 12:00:00');
    config()->set('hubdigital.qa_deposit_id', $mismoExpediente ? (string) $solicitud->id : (string) Str::uuid());
    config()->set('hubdigital.qa_mail_to', 'qa@example.test');

    app(NotificacionCuratoriaAdapter::class)->notificarNuevaSolicitudPorRevisar((string) $solicitud->id);

    Notification::assertSentTo($curador, NuevaSolicitudPorRevisarNotification::class,
        fn ($notificacion, array $canales) => $canales === ['database']);
    if ($redirige) {
        Notification::assertSentOnDemand(NuevaSolicitudPorRevisarNotification::class,
            fn ($notificacion, array $canales, $destino) => ($destino->routes['mail'] ?? null) === 'qa@example.test'
                && $notificacion->solicitudId === (string) $solicitud->id && $canales === ['mail']);
    } else {
        Notification::assertNothingSentTo(new \Illuminate\Notifications\AnonymousNotifiable);
        Notification::assertSentTo($curador, NuevaSolicitudPorRevisarNotification::class,
            fn ($notificacion, array $canales) => $canales === ['mail']);
    }
})->with([
    'solo expediente local' => ['local', true, true],
    'otro expediente local' => ['local', false, false],
    'desarrollo' => ['staging', true, false],
    'producción' => ['production', true, false],
]);

test('la matriz de notificaciones delimita destinatarios canales y enlaces de depósitos', function (): void {
    Notification::fake();
    $depositante = User::factory()->depositante()->create();
    $curadorResponsable = User::factory()->curador()->create();
    $otroCurador = User::factory()->curador()->create();
    $administrador = User::factory()->administrador()->create();
    $ajeno = User::factory()->depositante()->create();
    $solicitud = SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => 'QA-NOTIF-20260911',
        'investigador_id' => (string) $depositante->id,
        'curador_responsable' => (string) $curadorResponsable->id,
        'tipo_tramite' => 'Depósito',
        'estado' => EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
    ]);

    $curaduria = app(NotificacionCuratoriaAdapter::class);
    $investigador = app(NotificacionInvestigadorAdapter::class);
    $curaduria->notificarNuevaSolicitudPorRevisar((string) $solicitud->id);
    $curaduria->notificarDecisionDocumentalAOtrosCuradores((string) $solicitud->id, (string) $curadorResponsable->id, 'aprobada');
    $curaduria->notificarLoteRecibidoParaActa((string) $solicitud->id, (string) $administrador->id, true);
    $investigador->notificarCodigoQrDisponible((string) $solicitud->id, (string) $depositante->id, 'LOTE-QANOTI');
    $investigador->notificarCorreccionesCuratoriales((string) $solicitud->id, (string) $depositante->id, [['campo' => 'localidad', 'anterior' => 'A', 'nuevo' => 'B', 'especie' => 'QA']]);
    $investigador->notificarRechazoSolicitud((string) $solicitud->id, (string) $depositante->id, 'Motivo sintético');
    $investigador->notificarRecepcionFinalizada((string) $solicitud->id, (string) $depositante->id, 'Pendiente de ingreso');
    $investigador->notificarRecepcionConObservaciones((string) $solicitud->id, (string) $depositante->id, ['Observación sintética']);
    $investigador->notificarOrdenAccionCorrectiva((string) $solicitud->id, (string) $depositante->id, 'Envase', 'Reponer envase');
    $investigador->notificarActaRecepcionDisponible((string) $solicitud->id, (string) $depositante->id);

    Notification::assertSentTo([$curadorResponsable, $otroCurador, $administrador], NuevaSolicitudPorRevisarNotification::class);
    Notification::assertNotSentTo([$depositante, $ajeno], NuevaSolicitudPorRevisarNotification::class);
    Notification::assertSentTo([$otroCurador, $administrador], DecisionDocumentalCuradorNotification::class);
    Notification::assertNotSentTo([$curadorResponsable, $depositante, $ajeno], DecisionDocumentalCuradorNotification::class);
    Notification::assertSentTo($curadorResponsable, LoteRecibidoParaActaNotification::class);
    Notification::assertNotSentTo([$otroCurador, $administrador, $depositante, $ajeno], LoteRecibidoParaActaNotification::class);

    foreach ([
        CodigoQrDisponibleNotification::class,
        CorreccionCuratorialNotification::class,
        SolicitudRechazadaNotification::class,
        RecepcionFinalizadaNotification::class,
        RecepcionConObservacionesNotification::class,
        OrdenAccionCorrectivaNotification::class,
        ActaRecepcionFirmadaNotification::class,
    ] as $clase) {
        Notification::assertSentTo($depositante, $clase);
        Notification::assertNotSentTo([$curadorResponsable, $otroCurador, $administrador, $ajeno], $clase);
    }

    $alertaActa = new LoteRecibidoParaActaNotification(
        (string) $solicitud->id,
        $solicitud->numero,
        $solicitud->tipo_tramite,
        'Receptor Sintético',
        true,
    );
    config()->set('webpush.vapid.subject', null);
    expect($alertaActa->via($curadorResponsable))->toBe(['mail', 'database'])
        ->and($alertaActa->toArray($curadorResponsable)['url'])->toContain((string) $solicitud->id)
        ->and($alertaActa->toArray($curadorResponsable)['eventoId'])->toBe('deposito-acta-'.$solicitud->id);

    config()->set('webpush.vapid.subject', 'mailto:qa@example.test');
    config()->set('webpush.vapid.public_key', 'publica-sintetica');
    config()->set('webpush.vapid.private_key', 'privada-sintetica');
    $curadorResponsable->updatePushSubscription('https://fcm.googleapis.com/fcm/send/qa-cinco-puntos', 'clave-publica', 'auth-token');
    expect($alertaActa->via($curadorResponsable))->toContain(WebPushChannel::class);
});
