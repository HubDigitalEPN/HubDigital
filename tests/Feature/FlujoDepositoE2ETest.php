<?php

declare(strict_types=1);

use App\Models\User;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\SolicitudFirmadaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaRecepcionFirmada\SubirActaRecepcionFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaRecepcionFirmada\SubirActaRecepcionFirmadaInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaRecepcionFirmada;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemChecklistRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\IngresarLoteEnColeccionListener;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\LoteRecibidoParaActaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Support\ProyectorDatosDepositoMepn;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeIngresoColeccionAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeNotificacionCuratoriaAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeNotificacionInvestigadorAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryMatrizEspeciesRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryRecepcionLoteRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudDepositoRepository;

test('flujo integral separa depositante receptor y curador hasta el acta final firmada', function (): void {
    $rutaActaFirmada = tempnam(sys_get_temp_dir(), 'acta-firmada-');
    $rutaActaOriginal = tempnam(sys_get_temp_dir(), 'acta-original-');
    $rutaSegundoIntento = tempnam(sys_get_temp_dir(), 'acta-segundo-');

    expect($rutaActaFirmada)->not->toBeFalse()
        ->and($rutaActaOriginal)->not->toBeFalse()
        ->and($rutaSegundoIntento)->not->toBeFalse();

    file_put_contents($rutaActaFirmada, "%PDF-1.7\n% acta firmada de prueba\n");
    file_put_contents($rutaActaOriginal, "%PDF-1.7\n% acta original de prueba\n");
    file_put_contents($rutaSegundoIntento, "%PDF-1.7\n% segundo intento de prueba\n");

    $depositante = User::factory()->depositante()->create([
        'first_name' => 'Consultora',
        'last_name' => 'MEPN Prueba',
        'email' => 'consultora.depositante@example.test',
        'cargo' => 'Especialista ambiental',
        'institucion' => 'Consultora de prueba E2E',
    ]);
    $receptor = User::factory()->receptor()->create([
        'first_name' => 'Receptor',
        'last_name' => 'EPN Prueba',
        'email' => 'receptor@example.test',
    ]);
    $curador = User::factory()->curador()->create([
        'first_name' => 'Curadora',
        'last_name' => 'EPN Prueba',
        'email' => 'curadora@example.test',
    ]);

    $solicitudes = new InMemorySolicitudDepositoRepository;
    $recepciones = new InMemoryRecepcionLoteRepository;
    $matrices = new InMemoryMatrizEspeciesRepository;
    $eventos = new FakeEventPublisherAdapter;

    app()->instance(SolicitudDepositoRepositoryInterface::class, $solicitudes);
    app()->instance(RecepcionLoteRepositoryInterface::class, $recepciones);
    app()->instance(MatrizEspeciesRepositoryInterface::class, $matrices);
    app()->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
    app()->instance(EventPublisherPort::class, $eventos);
    app()->instance(NotificacionInvestigadorPort::class, new FakeNotificacionInvestigadorAdapter);
    $notificacionesCuraduria = new FakeNotificacionCuratoriaAdapter;
    app()->instance(NotificacionCuratoriaPort::class, $notificacionesCuraduria);
    app()->instance(SolicitudFirmadaPort::class, new class implements SolicitudFirmadaPort
    {
        public function estaFirmada(string $solicitudId): bool
        {
            return true;
        }
    });
    $ingresoColeccion = new FakeIngresoColeccionAdapter;
    app()->instance(IngresoColeccionPort::class, $ingresoColeccion);
    app()->instance(ValidacionFirmaElectronicaPort::class, new class implements ValidacionFirmaElectronicaPort
    {
        public function verificarFirma(string $rutaAbsoluta): ResultadoValidacionFirma
        {
            return ResultadoValidacionFirma::Firmado;
        }

        public function verificarFirmaDetallada(string $rutaFirmadaAbsoluta, string $rutaOriginalAbsoluta): DetalleValidacionFirma
        {
            return new DetalleValidacionFirma(
                resultado: ResultadoValidacionFirma::Firmado,
                integridadCriptografica: true,
                documentoCompletoFirmado: true,
                contenidoOficialCoincide: true,
                certificadoVigente: true,
                certificadoConfiable: true,
                aceptadaPorMotor: true,
                formatoFirmaAceptado: true,
                certificado: [
                    'nombre' => 'Curadora EPN Prueba',
                    'tipo_firma' => 'ETSI.CAdES.detached',
                ],
            );
        }
    });

    $solicitud = SolicitudDeposito::crear(
        id: $solicitudes->nextIdentity(),
        numero: $solicitudes->nextNumero(),
        investigadorId: (string) $depositante->id,
        tipoTramite: 'Depósito',
    );
    $solicitudes->guardar($solicitud);

    $matriz = MatrizEspecies::crear(
        id: $matrices->nextIdentity(),
        solicitudId: (string) $solicitud->id(),
        camposDwCPresentes: ['scientificName' => true],
        tipoTramite: $solicitud->tipoTramite(),
    );
    $registroId = $matriz->agregarRegistroEspecimen('Danaus plexippus');
    $matriz->validarRegistroCatalogado($registroId);
    $matrices->guardar($matriz);

    app(EnviarSolicitudDepositoHandler::class)(new EnviarSolicitudDepositoInput(
        solicitudId: (string) $solicitud->id(),
    ));

    app(AprobarDocumentalmenteSolicitudHandler::class)(new AprobarDocumentalmenteSolicitudInput(
        solicitudId: (string) $solicitud->id(),
        curadorId: (string) $curador->id,
    ));
    app(IniciarRecepcionLoteHandler::class)(new IniciarRecepcionLoteInput(
        solicitudId: (string) $solicitud->id(),
        receptorId: (string) $receptor->id,
    ));
    app(AprobarRecepcionLoteHandler::class)(new AprobarRecepcionLoteInput(
        solicitudId: (string) $solicitud->id(),
        receptorId: (string) $receptor->id,
        itemsVerificacion: array_map(
            static fn (ItemChecklistRecepcion $item): array => [
                'item' => $item->value,
                'resultado' => $item->resultadoConforme(),
            ],
            ItemChecklistRecepcion::cases(),
        ),
    ));

    $loteRecibido = $recepciones->buscarPorSolicitudId($solicitud->id());
    expect($loteRecibido)
        ->not->toBeNull()
        ->and($loteRecibido->recibidoPor())->toBe((string) $receptor->id)
        ->and($loteRecibido->actaEmitida())->toBeFalse()
        ->and($notificacionesCuraduria->alertasActa())->toBe([[
            'solicitudId' => (string) $solicitud->id(),
            'receptorId' => (string) $receptor->id,
            'conObservaciones' => false,
        ]])
        ->and($ingresoColeccion->estadoColeccionDe((string) $solicitud->id()))->toBeNull();

    app(GenerarActaRecepcionHandler::class)(new GenerarActaRecepcionInput(
        solicitudId: (string) $solicitud->id(),
        curadorId: (string) $curador->id,
    ));
    $referenciaOriginal = '018f45f0-7c99-7ae4-8f72-4b320b11c777';
    $sha256Original = hash_file('sha256', $rutaActaOriginal);
    $recepciones->registrarOriginalVigente($solicitud->id(), $referenciaOriginal, $sha256Original);
    app(SubirActaRecepcionFirmadaHandler::class)(new SubirActaRecepcionFirmadaInput(
        solicitudId: (string) $solicitud->id(),
        curadorId: (string) $curador->id,
        rutaRelativa: 'actas/recepcion-firmada/prueba.pdf',
        rutaAbsoluta: $rutaActaFirmada,
        rutaOriginalAbsoluta: $rutaActaOriginal,
        referenciaOriginal: $referenciaOriginal,
        sha256Original: $sha256Original,
    ));

    $listener = app(IngresarLoteEnColeccionListener::class);
    foreach ($eventos->publishedEvents() as $evento) {
        if ($evento instanceof ActaRecepcionFirmada) {
            $listener->handle($evento);
        }
    }

    $loteFinal = $recepciones->buscarPorSolicitudId($solicitud->id());
    expect($loteFinal->actaEmitida())->toBeTrue()
        ->and($loteFinal->actaFirmada())->toBeTrue()
        ->and($loteFinal->actaFirmadaRuta())->toBe('actas/recepcion-firmada/prueba.pdf')
        ->and($loteFinal->actaGeneradaPor())->toBe((string) $curador->id)
        ->and($loteFinal->firmaMetadata()['firmante_usuario_id'])->toBe((string) $curador->id)
        ->and($loteFinal->firmaMetadata()['proposito'])->toBe('acta_final_recepcion')
        ->and($loteFinal->firmaMetadata()['integridad_criptografica'])->toBeTrue()
        ->and($loteFinal->firmaMetadata()['contenido_oficial_coincide'])->toBeTrue()
        ->and($ingresoColeccion->estadoColeccionDe((string) $solicitud->id()))->toBe('Temporal');

    expect(fn () => app(SubirActaRecepcionFirmadaHandler::class)(new SubirActaRecepcionFirmadaInput(
        solicitudId: (string) $solicitud->id(),
        curadorId: (string) $curador->id,
        rutaRelativa: 'actas/recepcion-firmada/intento-segundo.pdf',
        rutaAbsoluta: $rutaSegundoIntento,
        rutaOriginalAbsoluta: $rutaActaOriginal,
        referenciaOriginal: $referenciaOriginal,
        sha256Original: $sha256Original,
    )))->toThrow(\DomainException::class, 'El acta ya fue firmada');

    expect($recepciones->buscarPorSolicitudId($solicitud->id())?->actaFirmadaRuta())
        ->toBe('actas/recepcion-firmada/prueba.pdf');

    @unlink($rutaActaFirmada);
    @unlink($rutaActaOriginal);
    @unlink($rutaSegundoIntento);
});

test('proyecta exactamente las quince columnas de Datos deposito material MEPN', function (): void {
    $depositante = new User([
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'cargo' => 'Consultora ambiental',
        'institucion' => 'BioConsultores S.A.',
    ]);
    $solicitud = new SolicitudDepositoEloquentModel([
        'nombre_investigador_documento' => 'Ana Pérez',
        'nro_permiso_recoleccion' => '026-2026-OTOR-DZ8-MAE',
        'nro_permiso_movilizacion' => '099-2026-MAE-DZ8-OTMA',
        'grupo_animal' => 'Insectos',
        'nro_individuos' => 37,
        'nro_morfoespecies' => 4,
        'nro_lotes' => 2,
        'localidad' => 'Mechero B60',
        'provincia_origen' => 'Orellana',
    ]);
    $recepcion = new RecepcionLoteEloquentModel([
        'codigo_qr' => 'LOTE-ABC123',
        'verificado_en' => '2026-09-03 14:00:00',
        'observaciones' => ['Frascos constatados'],
        'estado' => 'Verificado Físicamente',
    ]);

    $datos = (new ProyectorDatosDepositoMepn)->proyectar($solicitud, $depositante, $recepcion);

    expect(array_keys($datos))->toBe([
        'A. Nombre representante legal empresa',
        'B. Cargo o posición',
        'C. Empresa o institución',
        'D. No. permiso recolección',
        'E. No. permiso movilización',
        'F. Grupo animal',
        'G. No. individuos',
        'H. No. de morfoespecies',
        'I. No. de lotes',
        'J. Localidad',
        'K. No. de proceso (uso interno)',
        'L. Fecha de recepción de los especímenes (uso interno)',
        'M. Período (uso interno)',
        'N. Observaciones (uso interno)',
        'O. Estado (uso interno)',
    ])->and($datos['J. Localidad'])->toBe('Mechero B60 (Orellana)')
        ->and($datos['K. No. de proceso (uso interno)'])->toBe('LOTE-ABC123');
});

test('la alerta curatorial abre directamente el acta del lote constatado', function (): void {
    $notificacion = new LoteRecibidoParaActaNotification(
        solicitudId: '018f45f0-7c99-7ae4-8f72-4b320b11c777',
        numero: 'DEP-2026-0042',
        tipoTramite: 'Depósito',
        nombreReceptor: 'Recepción EPN',
        conObservaciones: false,
    );

    $datos = $notificacion->toArray(new User);

    expect($datos['tipo'])->toBe('lote_recibido_acta_pendiente')
        ->and($datos['prioridad'])->toBe('alta')
        ->and($datos['accion'])->toBe('Generar y firmar acta')
        ->and($datos['url'])->toContain('/prestamos/curador/deposito/018f45f0-7c99-7ae4-8f72-4b320b11c777/acta-final')
        ->and($notificacion->afterCommit)->toBeTrue();
});
