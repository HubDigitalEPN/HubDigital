<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoEspecimenesPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EspecimenCatalogoDto;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo\GenerarActaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo\GenerarActaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDevueltaPorFirmaInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Support\FakeCatalogoEspecimenesPort;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudPrestamoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: envio_solicitud_prestamo.feature
 * Capability: TramitacionSolicitudesInvestigador
 */
final class EnvioSolicitudPrestamoContext extends BaseContext
{
    // ── Repositorios in-memory (acceso directo para @Given y @Then) ──────────

    private InMemorySolicitudPrestamoRepository $solicitudRepo;

    private InMemoryActaPrestamoRepository $actaRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    private FakeCatalogoEspecimenesPort $fakeCatalogo;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarSolicitudPrestamoHandler $registrarHandler;

    private ActualizarSolicitudPrestamoHandler $actualizarHandler;

    private EnviarSolicitudPrestamoHandler $enviarHandler;

    private GenerarActaPrestamoHandler $generarActaHandler;

    private SubirActaFirmadaHandler $subirActaHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?SolicitudPrestamo $solicitudExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $investigadorId = 'inv-001';

    private string $curadorId = 'cur-001';

    private array $datosSolicitudCompleta = [
        'titulo_estudio' => 'Revisión taxonómica del género Morpho en Ecuador',
        'institucion_adscripcion' => 'Universidad Central del Ecuador',
        'linea_investigacion' => 'Entomología sistemática',
        'proposito_prestamo' => 'Estudio comparativo de morfología alar',
        'duracion_propuesta_meses' => 3,
        'items' => [
            ['especimen_id' => self::ESPECIMEN_1, 'especimen_codigo_externo' => 'ESP-001', 'cantidad_solicitada' => 2],
            ['especimen_id' => self::ESPECIMEN_2, 'especimen_codigo_externo' => 'ESP-002', 'cantidad_solicitada' => 1],
        ],
    ];

    private const ESPECIMEN_1 = '11111111-1111-4111-8111-111111111111';

    private const ESPECIMEN_2 = '22222222-2222-4222-8222-222222222222';

    // ── Constructor — registra dependencias in-memory antes de resolver Handlers

    public function __construct()
    {
        self::bootApp();

        // 1. Crear instancias in-memory fresh para este escenario
        $this->solicitudRepo = new InMemorySolicitudPrestamoRepository;
        $this->actaRepo = new InMemoryActaPrestamoRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;
        $this->fakeCatalogo = $this->sembrarCatalogo();

        // 2. Interceptar el container para que los Handlers reciban estas instancias
        self::$app->instance(SolicitudPrestamoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(ActaPrestamoRepositoryInterface::class, $this->actaRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(CatalogoEspecimenesPort::class, $this->fakeCatalogo);

        // 3. Resolver Handlers — ya usan las instancias in-memory
        $this->registrarHandler = $this->make(RegistrarSolicitudPrestamoHandler::class);
        $this->actualizarHandler = $this->make(ActualizarSolicitudPrestamoHandler::class);
        $this->enviarHandler = $this->make(EnviarSolicitudPrestamoHandler::class);
        $this->generarActaHandler = $this->make(GenerarActaPrestamoHandler::class);
        $this->subirActaHandler = $this->make(SubirActaFirmadaHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    /** Puebla el catálogo falso con los especímenes que referencian los items de prueba. */
    private function sembrarCatalogo(): FakeCatalogoEspecimenesPort
    {
        $catalogo = new FakeCatalogoEspecimenesPort;

        foreach ($this->datosSolicitudCompleta['items'] as $item) {
            $catalogo->agregar(new EspecimenCatalogoDto(
                especimenId: $item['especimen_id'],
                codigoCatalogo: $item['especimen_codigo_externo'],
                nombreCientifico: 'Morpho '.$item['especimen_codigo_externo'],
                individualesDisponibles: $item['cantidad_solicitada'],
                estado: 'disponible',
            ));
        }

        return $catalogo;
    }

    private function sembrarSolicitudBase(): SolicitudPrestamo
    {
        $items = array_map(
            fn (array $item) => ItemPrestamo::crear(
                id: ItemPrestamoId::generate(),
                especimenId: $item['especimen_id'],
                especimenCodigoExterno: $item['especimen_codigo_externo'],
                cantidadSolicitada: $item['cantidad_solicitada'],
            ),
            $this->datosSolicitudCompleta['items'],
        );

        $solicitud = SolicitudPrestamo::crear(
            id: $this->solicitudRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            investigadorId: $this->investigadorId,
            alcancePrestamo: AlcancePrestamo::Nacional,
            tituloEstudio: $this->datosSolicitudCompleta['titulo_estudio'],
            institucionAdscripcion: $this->datosSolicitudCompleta['institucion_adscripcion'],
            lineaInvestigacion: $this->datosSolicitudCompleta['linea_investigacion'],
            propositoPrestamo: $this->datosSolicitudCompleta['proposito_prestamo'],
            duracionPropuestaMeses: $this->datosSolicitudCompleta['duracion_propuesta_meses'],
            items: $items,
        );

        $this->solicitudRepo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    private function sembrarSolicitudIncompleta(): SolicitudPrestamo
    {
        $solicitud = SolicitudPrestamo::crearIncompleta(
            id: $this->solicitudRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            investigadorId: $this->investigadorId,
            alcancePrestamo: AlcancePrestamo::Nacional,
        );

        $this->solicitudRepo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    // =========================================================================
    // ESCENARIO: Guardar una solicitud como borrador
    // =========================================================================

    #[Given('que el investigador ha ingresado información en una solicitud')]
    public function queElInvestigadorHaIngresadoInformacionEnUnaSolicitud(): void
    {
        Assert::assertNotEmpty($this->investigadorId);
        Assert::assertNotEmpty($this->datosSolicitudCompleta['titulo_estudio']);
        Assert::assertNotEmpty($this->datosSolicitudCompleta['institucion_adscripcion']);
        Assert::assertNotEmpty($this->datosSolicitudCompleta['linea_investigacion']);
        Assert::assertNotEmpty($this->datosSolicitudCompleta['proposito_prestamo']);
        Assert::assertGreaterThan(0, $this->datosSolicitudCompleta['duracion_propuesta_meses']);
        Assert::assertNotEmpty($this->datosSolicitudCompleta['items']);

        $this->sembrarSolicitudBase();
    }

    #[When('el investigador registra la solicitud')]
    public function elInvestigadorRegistraLaSolicitud(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarSolicitudPrestamoInput(
                    investigadorId: $this->investigadorId,
                    alcancePrestamo: AlcancePrestamo::Nacional->value,
                    tituloEstudio: $this->datosSolicitudCompleta['titulo_estudio'],
                    institucionAdscripcion: $this->datosSolicitudCompleta['institucion_adscripcion'],
                    lineaInvestigacion: $this->datosSolicitudCompleta['linea_investigacion'],
                    propositoPrestamo: $this->datosSolicitudCompleta['proposito_prestamo'],
                    duracionPropuestaMeses: $this->datosSolicitudCompleta['duracion_propuesta_meses'],
                    items: $this->datosSolicitudCompleta['items'],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud queda registrada en estado borrador')]
    public function laSolicitudQuedaRegistradaEnEstadoBorrador(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitud::Borrador),
            "Se esperaba estado 'borrador', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Editar una solicitud en estado borrador
    // =========================================================================

    #[Given('que el investigador tiene una solicitud en estado borrador')]
    public function queExisteUnaSolicitudEnEstadoBorrador(): void
    {
        $solicitud = $this->sembrarSolicitudBase();
        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());

        Assert::assertNotNull($persistida);
        Assert::assertTrue($persistida->estado()->equals(EstadoSolicitud::Borrador));
    }

    #[Given('el investigador tiene acceso a dicha solicitud')]
    public function elInvestigadorTieneAccesoADichaSolicitud(): void
    {
        Assert::assertNotNull($this->solicitudExistente);
        Assert::assertSame($this->investigadorId, $this->solicitudExistente->investigadorId());
    }

    #[When('el investigador actualiza la información de la solicitud')]
    public function elInvestigadorActualizaLaInformacionDeLaSolicitud(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        $tituloNuevo = 'Morfología comparada de Lepidoptera neotropicales';
        $institucionNueva = 'Escuela Politécnica Nacional';
        $lineaNueva = 'Biología evolutiva';
        $propositoNuevo = 'Análisis filogenético de caracteres morfológicos';
        $duracionNueva = 6;

        Assert::assertNotSame($tituloNuevo, $this->solicitudExistente->tituloEstudio());
        Assert::assertNotSame($institucionNueva, $this->solicitudExistente->institucionAdscripcion());

        try {
            $this->ultimaRespuesta = $this->actualizarHandler->handle(
                new ActualizarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    investigadorId: $this->investigadorId,
                    tituloEstudio: $tituloNuevo,
                    institucionAdscripcion: $institucionNueva,
                    lineaInvestigacion: $lineaNueva,
                    propositoPrestamo: $propositoNuevo,
                    duracionPropuestaMeses: $duracionNueva,
                    items: $this->datosSolicitudCompleta['items'],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud refleja la información actualizada')]
    public function laSolicitudReflejaLaInformacionActualizada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame('Morfología comparada de Lepidoptera neotropicales', $this->ultimaRespuesta->tituloEstudio);
        Assert::assertSame('Escuela Politécnica Nacional', $this->ultimaRespuesta->institucionAdscripcion);
        Assert::assertSame('Biología evolutiva', $this->ultimaRespuesta->lineaInvestigacion);
        Assert::assertSame('Análisis filogenético de caracteres morfológicos', $this->ultimaRespuesta->propositoPrestamo);
        Assert::assertSame(6, $this->ultimaRespuesta->duracionPropuestaMeses);
    }

    #[Then('la solicitud sigue en estado borrador')]
    public function laSolicitudPermanenceEnEstadoBorrador(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitud::Borrador),
            "Se esperaba estado 'borrador' tras edición, se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Enviar una solicitud con información completa
    // =========================================================================

    #[Given('que existe una solicitud en estado :estado_previo con su información requerida completa')]
    public function queExisteUnaSolicitudEnEstadoConInformacionCompleta(string $estado_previo): void
    {
        $solicitud = $this->sembrarSolicitudBase();

        if ($estado_previo === 'observada') {
            $solicitud->enviar();
            $solicitud->observar(
                curadorId: $this->curadorId,
                observacion: 'Requiere información adicional sobre el período de estudio',
            );
            $this->solicitudRepo->guardar($solicitud);
        }

        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::from($estado_previo)),
            "Se esperaba estado '{$estado_previo}', se obtuvo: {$persistida->estado()->value}"
        );
        Assert::assertNotNull($persistida->tituloEstudio());
        Assert::assertNotNull($persistida->institucionAdscripcion());
        Assert::assertNotNull($persistida->lineaInvestigacion());
        Assert::assertNotNull($persistida->propositoPrestamo());
        Assert::assertGreaterThan(0, $persistida->duracionPropuestaMeses());
        Assert::assertNotEmpty($persistida->items());
    }

    #[When('el investigador envía la solicitud')]
    public function elInvestigadorEnviaLaSolicitud(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $this->ultimaRespuesta = $this->enviarHandler->handle(
                new EnviarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    investigadorId: $this->investigadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud queda en estado enviada')]
    public function laSolicitudQuedaEnEstadoEnviada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitud::Enviada),
            "Se esperaba estado 'enviada', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
        Assert::assertNotNull(
            $this->ultimaRespuesta->enviadaEn,
            "Se esperaba que 'enviada_en' quedara registrado"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: No permitir enviar una solicitud con información incompleta
    // =========================================================================

    #[Given('que existe una solicitud en estado :estado_previo con información incompleta')]
    public function queExisteUnaSolicitudEnEstadoConInformacionIncompleta(string $estado_previo): void
    {
        if ($estado_previo === 'observada') {
            // No es posible llegar a 'observada' por flujo normal con datos incompletos
            // (enviar() valida estaCompleta()). Se reconstituye directamente.
            $solicitud = SolicitudPrestamo::reconstituir(
                id: $this->solicitudRepo->nextIdentity(),
                codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
                investigadorId: $this->investigadorId,
                alcancePrestamo: AlcancePrestamo::Nacional,
                estado: EstadoSolicitud::Observada,
                tituloEstudio: null,
                institucionAdscripcion: null,
                lineaInvestigacion: null,
                propositoPrestamo: null,
                duracionPropuestaMeses: null,
                justificacionExtendida: null,
                comentarioCurador: 'Requiere información adicional sobre el período de estudio',
                items: [],
                enviadaEn: null,
                resueltaEn: null,
                resueltaPor: null,
            );
            $this->solicitudRepo->guardar($solicitud);
            $this->solicitudExistente = $solicitud;
        } else {
            $solicitud = $this->sembrarSolicitudIncompleta();
        }

        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::from($estado_previo)),
            "Se esperaba estado '{$estado_previo}', se obtuvo: {$persistida->estado()->value}"
        );
    }

    #[Then('la solicitud permanece en estado :estado_previo')]
    public function laSolicitudPermanenceEnEstado(string $estado_previo): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el envío fallara (solicitud incompleta) pero el handler completó sin error'
        );

        $solicitud = $this->solicitudRepo->buscarPorId($this->solicitudExistente->id());
        Assert::assertNotNull($solicitud);
        Assert::assertTrue(
            $solicitud->estado()->equals(EstadoSolicitud::from($estado_previo)),
            "Se esperaba estado '{$estado_previo}', se obtuvo: {$solicitud->estado()->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Recibir el acta de préstamo para firma
    // =========================================================================

    #[Given('que existe una solicitud del investigador en estado aprobada')]
    public function queExisteUnaSolicitudEnEstadoAprobada(): void
    {
        $solicitud = $this->sembrarSolicitudBase();
        $solicitud->enviar();
        $solicitud->aprobar(curadorId: $this->curadorId);
        $this->solicitudRepo->guardar($solicitud);
    }

    #[When('el acta de préstamo es generada')]
    public function elActaDePrestamoEsGenerada(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $this->ultimaRespuesta = $this->generarActaHandler->handle(
                new GenerarActaPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el investigador recibe una notificación con el acta para su firma')]
    public function elInvestigadorRecibeUnaNotificacionConElActaParaSuFirma(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionEnviada,
            'Se esperaba que la notificación al investigador fuera enviada'
        );
        Assert::assertNotEmpty($this->ultimaRespuesta->pdfRuta);
        Assert::assertNull(
            $this->ultimaRespuesta->pdfFirmadoRuta,
            "Se esperaba que 'pdf_firmado_ruta' fuera null — el acta aún no ha sido firmada"
        );
    }

    // =========================================================================
    // ESCENARIO: Firmar y enviar el acta de préstamo
    // =========================================================================

    #[Given('que el investigador ha recibido el acta de préstamo')]
    public function queElInvestigadorHaRecibidoElActaDePrestamo(): void
    {
        $solicitud = $this->sembrarSolicitudBase();
        $solicitud->enviar();
        $solicitud->aprobar(curadorId: $this->curadorId);
        $this->solicitudRepo->guardar($solicitud);

        $pdfRuta = 'actas/'.(string) $solicitud->id().'.pdf';
        $solicitud->emitirActa($pdfRuta);

        $ahora = new DateTimeImmutable;
        $meses = $solicitud->duracionPropuestaMeses() ?? 3;
        $fechaFin = $ahora->modify("+{$meses} months");

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            solicitudPrestamoId: $solicitud->id(),
            tipoPrestamo: TipoPrestamo::Temporal,
            alcancePrestamo: AlcancePrestamo::Nacional,
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
        );

        // Transiciona a PendienteFirma para que SubirActaFirmadaHandler pueda operar
        $acta->marcarEnviada($this->investigadorId);

        $this->actaRepo->guardar($acta);
    }

    #[When('el investigador sube el acta firmada')]
    public function elInvestigadorSubeElActaFirmada(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $this->ultimaRespuesta = $this->subirActaHandler->handle(
                new SubirActaFirmadaInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    investigadorId: $this->investigadorId,
                    pdfFirmadoRuta: 'actas/firmadas/MEPN-INV-001-2026-firmada.pdf',
                    documentoIdentidadRuta: 'documentos-identidad/'.(string) $this->solicitudExistente->id().'-id.pdf',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el acta queda en estado pendiente de validación')]
    public function elActaQuedaPendienteDeValidacion(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(
            'pendiente_validacion',
            $this->ultimaRespuesta->estadoActa,
            "Se esperaba estado 'pendiente_validacion' del acta"
        );
        Assert::assertNotEmpty($this->ultimaRespuesta->pdfFirmadoRuta);
        Assert::assertNotNull($this->ultimaRespuesta->firmadaSubidaEn);
    }

    // =========================================================================
    // ESCENARIO: Recibir notificación de devolución del acta por firma inválida
    // =========================================================================

    #[Given('que el investigador ha subido el acta firmada')]
    public function queElInvestigadorHaSubidoElActaFirmada(): void
    {
        $solicitud = $this->sembrarSolicitudBase();
        $solicitud->enviar();
        $solicitud->aprobar(curadorId: $this->curadorId);
        $this->solicitudRepo->guardar($solicitud);

        $pdfRuta = 'actas/'.(string) $solicitud->id().'.pdf';
        $solicitud->emitirActa($pdfRuta);

        $ahora = new DateTimeImmutable;
        $meses = $solicitud->duracionPropuestaMeses() ?? 3;
        $fechaFin = $ahora->modify("+{$meses} months");

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            solicitudPrestamoId: $solicitud->id(),
            tipoPrestamo: TipoPrestamo::Temporal,
            alcancePrestamo: AlcancePrestamo::Nacional,
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
        );

        $acta->marcarEnviada($this->investigadorId);
        $acta->subirFirma(
            'actas/firmadas/MEPN-INV-001-2026-firmada.pdf',
            'documentos-identidad/MEPN-INV-001-2026-id.pdf',
        );
        $this->actaRepo->guardar($acta);
    }

    #[When('el curador devuelve el acta por motivos de firma')]
    public function elCuradorDevuelveElActaPorMotivosDeFirma(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $acta = $this->actaRepo->buscarPorSolicitudId($this->solicitudExistente->id());
            Assert::assertNotNull($acta, 'No se encontró el acta para la solicitud existente');

            $acta->devolver(
                investigadorId: $this->investigadorId,
                motivo: 'La firma no es válida, debe usar firma electrónica certificada.',
            );

            foreach ($acta->pullEvents() as $event) {
                $this->fakePublisher->publish($event);
            }

            $this->actaRepo->guardar($acta);
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el investigador recibe una notificación con el motivo de la devolución')]
    public function elInvestigadorRecibeUnaNotificacionConElMotivoDeLaDevolucion(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El proceso de devolución lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        $evento = null;

        foreach ($this->fakePublisher->publishedEvents() as $e) {
            if ($e instanceof ActaDevueltaPorFirmaInvalida) {
                $evento = $e;
                break;
            }
        }

        Assert::assertNotNull(
            $evento,
            'Se esperaba que el evento ActaDevueltaPorFirmaInvalida fuera publicado'
        );
        Assert::assertNotEmpty($evento->motivo, 'El motivo de devolución no debe estar vacío');
    }
}
