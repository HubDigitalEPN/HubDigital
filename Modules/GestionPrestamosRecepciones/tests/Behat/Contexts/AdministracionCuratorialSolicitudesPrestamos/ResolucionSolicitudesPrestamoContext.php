<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo\AprobarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo\AprobarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo\ObservarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo\ObservarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarSolicitudPrestamo\RechazarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarSolicitudPrestamo\RechazarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitud;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudPrestamoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: resolucion_solicitudes_prestamo.feature
 * Capability: AdministracionCuratorialSolicitudesPrestamos
 */
final class ResolucionSolicitudesPrestamoContext extends BaseContext
{
    // ── Repositorios in-memory ────────────────────────────────────────────────

    private InMemorySolicitudPrestamoRepository $solicitudRepo;

    private InMemoryActaPrestamoRepository $actaRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private AprobarSolicitudPrestamoHandler $aprobarHandler;

    private ObservarSolicitudPrestamoHandler $observarHandler;

    private RechazarSolicitudPrestamoHandler $rechazarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?SolicitudPrestamo $solicitudExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    /** @var array<string, string> itemId => condicion */
    private array $condicionesPorItem = [];

    private string $curadorId = 'cur-001';

    private array $datosSolicitud = [
        'titulo_estudio' => 'Revisión taxonómica del género Morpho en Ecuador',
        'institucion_adscripcion' => 'Universidad Central del Ecuador',
        'linea_investigacion' => 'Entomología sistemática',
        'proposito_prestamo' => 'Estudio comparativo de morfología alar',
        'duracion_propuesta_meses' => 3,
        'items' => [
            ['especimen_codigo_externo' => 'ESP-001', 'cantidad_solicitada' => 2],
            ['especimen_codigo_externo' => 'ESP-002', 'cantidad_solicitada' => 1],
        ],
    ];

    // ── Constructor — registra dependencias in-memory antes de resolver Handlers

    public function __construct()
    {
        self::bootApp();

        $this->solicitudRepo = new InMemorySolicitudPrestamoRepository;
        $this->actaRepo = new InMemoryActaPrestamoRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;

        self::$app->instance(SolicitudPrestamoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(ActaPrestamoRepositoryInterface::class, $this->actaRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);

        $this->aprobarHandler = $this->make(AprobarSolicitudPrestamoHandler::class);
        $this->observarHandler = $this->make(ObservarSolicitudPrestamoHandler::class);
        $this->rechazarHandler = $this->make(RechazarSolicitudPrestamoHandler::class);
    }

    // ── Helper de fixture ─────────────────────────────────────────────────────

    private function sembrarSolicitudEnviada(): SolicitudPrestamo
    {
        $items = array_map(
            fn (array $item) => ItemPrestamo::crear(
                id: ItemPrestamoId::generate(),
                especimenCodigoExterno: $item['especimen_codigo_externo'],
                cantidadSolicitada: $item['cantidad_solicitada'],
            ),
            $this->datosSolicitud['items'],
        );

        $solicitud = SolicitudPrestamo::crear(
            id: $this->solicitudRepo->nextIdentity(),
            numeroSolicitud: NumeroSolicitud::generate(),
            investigadorId: 'inv-001',
            alcancePrestamo: AlcancePrestamo::Nacional,
            tituloEstudio: $this->datosSolicitud['titulo_estudio'],
            institucionAdscripcion: $this->datosSolicitud['institucion_adscripcion'],
            lineaInvestigacion: $this->datosSolicitud['linea_investigacion'],
            propositoPrestamo: $this->datosSolicitud['proposito_prestamo'],
            duracionPropuestaMeses: $this->datosSolicitud['duracion_propuesta_meses'],
            items: $items,
        );

        $solicitud->enviar();
        $solicitud->pullEvents(); // descartar eventos de creación/envío para no contaminar aserciones
        $this->solicitudRepo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    // =========================================================================
    // DADO COMPARTIDO: que existe una solicitud en estado enviada
    // Usado por todos los escenarios de esta feature
    // =========================================================================

    #[Given('que existe una solicitud de préstamo enviada para resolución')]
    public function queExisteUnaSolicitudEnEstadoEnviada(): void
    {
        $solicitud = $this->sembrarSolicitudEnviada();
        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());

        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada tras sembrarla');
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::Enviada),
            "Precondición fallida: se esperaba estado 'enviada', se obtuvo: {$persistida->estado()->value}"
        );
        Assert::assertNotEmpty($persistida->items(), 'La solicitud debe tener al menos un ítem');
    }

    // =========================================================================
    // ESCENARIO: Aprobar una solicitud de préstamo sin condiciones
    // =========================================================================

    #[When('el curador registra la aprobación de la solicitud con un tipo de préstamo y una duración establecida')]
    public function elCuradorRegistraLaAprobacionSinCondiciones(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $this->ultimaRespuesta = $this->aprobarHandler->handle(
                new AprobarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                    tipoPrestamo: 'temporal',
                    duracionMeses: 3,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud queda en estado aprobada')]
    public function laSolicitudQuedaEnEstadoAprobada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(
            EstadoSolicitud::Aprobada->value,
            $this->ultimaRespuesta->estadoSolicitud,
            "Se esperaba estado 'aprobada', se obtuvo: {$this->ultimaRespuesta->estadoSolicitud}"
        );

        $persistida = $this->solicitudRepo->buscarPorId($this->solicitudExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::Aprobada),
            'El estado persistido en el repositorio no es Aprobada'
        );
    }

    #[Then('se genera el acta de préstamo con los datos de la solicitud')]
    public function seGeneraElActaDePrestamoConLosDatos(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->actaId,
            'Se esperaba que el acta tuviera un ID generado'
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->numeroPrestamo,
            'Se esperaba que el acta tuviera un número de préstamo'
        );
        Assert::assertSame(
            'temporal',
            $this->ultimaRespuesta->tipoPrestamo,
            "Se esperaba tipo de préstamo 'temporal'"
        );

        $acta = $this->actaRepo->buscarPorSolicitudId($this->solicitudExistente->id());
        Assert::assertNotNull($acta, 'El acta no fue encontrada en el repositorio');
    }

    // =========================================================================
    // ESCENARIO: Aprobar una solicitud de préstamo con condiciones
    // =========================================================================

    #[When('el curador registra la aprobación de la solicitud con un tipo de préstamo, una duración establecida')]
    public function elCuradorRegistraLaAprobacionConCondicionesWhenBase(): void
    {
        // Este When se combina con el Y siguiente antes de ejecutar el handler
        // Las condiciones se acumularán en $this->condicionesPorItem vía el paso "Y"
    }

    #[When('condiciones específicas para uno o más especímenes')]
    public function condicionesEspecificasParaEspecimenes(): void
    {
        Assert::assertNotNull($this->solicitudExistente);
        Assert::assertNotEmpty($this->solicitudExistente->items(), 'La solicitud no tiene ítems');

        $primerItem = $this->solicitudExistente->items()[0];
        $this->condicionesPorItem[(string) $primerItem->id()] = 'Requiere manipulación con guantes y luz UV';

        try {
            $this->ultimaRespuesta = $this->aprobarHandler->handle(
                new AprobarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                    tipoPrestamo: 'temporal',
                    duracionMeses: 3,
                    condicionesPorItem: $this->condicionesPorItem,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('cada ítem queda asociado a sus condiciones específicas cuando aplican')]
    public function cadaItemQuedaAsociadoASusCondicionesEspecificas(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        $persistida = $this->solicitudRepo->buscarPorId($this->solicitudExistente->id());
        Assert::assertNotNull($persistida);

        $itemsConCondicion = array_filter(
            $persistida->items(),
            fn (ItemPrestamo $i) => $i->condicionesEspecificas() !== null,
        );

        Assert::assertNotEmpty(
            $itemsConCondicion,
            'Se esperaba que al menos un ítem tuviera condiciones específicas persistidas'
        );

        foreach ($itemsConCondicion as $item) {
            Assert::assertNotEmpty($item->condicionesEspecificas());
        }
    }

    #[Then('se genera el acta de préstamo')]
    public function seGeneraElActaDePrestamo(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertNotEmpty($this->ultimaRespuesta->actaId);

        $acta = $this->actaRepo->buscarPorSolicitudId($this->solicitudExistente->id());
        Assert::assertNotNull($acta, 'El acta no fue encontrada en el repositorio');
    }

    // =========================================================================
    // ESCENARIO: Observar una solicitud de préstamo
    // =========================================================================

    #[When('el curador registra la observación de la solicitud con un comentario')]
    public function elCuradorRegistraLaObservacionConComentario(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        $comentario = 'Faltan datos sobre el permiso de investigación vigente.';
        Assert::assertNotEmpty($comentario, 'El comentario de prueba no puede estar vacío');

        try {
            $this->ultimaRespuesta = $this->observarHandler->handle(
                new ObservarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                    observacion: $comentario,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud queda en estado observada')]
    public function laSolicitudQuedaEnEstadoObservada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(
            EstadoSolicitud::Observada->value,
            $this->ultimaRespuesta->estadoSolicitud,
            "Se esperaba estado 'observada', se obtuvo: {$this->ultimaRespuesta->estadoSolicitud}"
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->comentarioCurador,
            'Se esperaba que el comentario del curador quedara registrado'
        );

        $persistida = $this->solicitudRepo->buscarPorId($this->solicitudExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue($persistida->estado()->equals(EstadoSolicitud::Observada));
        Assert::assertNotEmpty($persistida->comentarioCurador());
    }

    // =========================================================================
    // ESCENARIO: No permitir observar una solicitud sin comentario
    // =========================================================================

    #[When('el curador intenta registrar la observación de la solicitud sin comentario')]
    public function elCuradorIntentaRegistrarLaObservacionSinComentario(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $this->ultimaRespuesta = $this->observarHandler->handle(
                new ObservarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                    observacion: '',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // =========================================================================
    // ESCENARIO: Rechazar una solicitud de préstamo
    // =========================================================================

    #[When('el curador registra el rechazo de la solicitud con un comentario')]
    public function elCuradorRegistraElRechazoConComentario(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        $motivo = 'Los especímenes solicitados no están disponibles para préstamo externo.';
        Assert::assertNotEmpty($motivo, 'El motivo de prueba no puede estar vacío');

        try {
            $this->ultimaRespuesta = $this->rechazarHandler->handle(
                new RechazarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                    motivo: $motivo,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud queda en estado rechazada')]
    public function laSolicitudQuedaEnEstadoRechazada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(
            EstadoSolicitud::Rechazada->value,
            $this->ultimaRespuesta->estadoSolicitud,
            "Se esperaba estado 'rechazada', se obtuvo: {$this->ultimaRespuesta->estadoSolicitud}"
        );

        $persistida = $this->solicitudRepo->buscarPorId($this->solicitudExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue($persistida->estado()->equals(EstadoSolicitud::Rechazada));
    }

    // =========================================================================
    // ESCENARIO: No permitir rechazar una solicitud sin comentario
    // =========================================================================

    #[When('el curador intenta registrar el rechazo de la solicitud sin comentario')]
    public function elCuradorIntentaRegistrarElRechazoSinComentario(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $this->ultimaRespuesta = $this->rechazarHandler->handle(
                new RechazarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                    motivo: '',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // =========================================================================
    // ESCENARIO: No permitir aprobar una solicitud sin tipo de préstamo
    // =========================================================================

    #[When('el curador intenta registrar la aprobación sin un tipo de préstamo')]
    public function elCuradorIntentaRegistrarLaAprobacionSinTipoPrestamo(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $this->ultimaRespuesta = $this->aprobarHandler->handle(
                new AprobarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                    tipoPrestamo: '',
                    duracionMeses: 3,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // =========================================================================
    // ESCENARIO: Aprobar una solicitud con condiciones generales
    // =========================================================================

    #[When('el curador registra la aprobación con condiciones generales para el préstamo')]
    public function elCuradorRegistraLaAprobacionConCondicionesGenerales(): void
    {
        Assert::assertNotNull($this->solicitudExistente);

        try {
            $this->ultimaRespuesta = $this->aprobarHandler->handle(
                new AprobarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId: $this->curadorId,
                    tipoPrestamo: 'temporal',
                    duracionMeses: 3,
                    condicionesGenerales: 'Manipulación con guantes y en ambiente controlado.',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el acta registra las condiciones generales del préstamo')]
    public function elActaRegistraLasCondicionesGenerales(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->condicionesGenerales,
            'Se esperaba que el acta tuviera condiciones generales registradas'
        );

        $acta = $this->actaRepo->buscarPorSolicitudId($this->solicitudExistente->id());
        Assert::assertNotNull($acta, 'El acta no fue encontrada en el repositorio');
        Assert::assertNotEmpty($acta->condicionesGenerales());
    }

    // =========================================================================
    // THEN COMPARTIDO: la solicitud permanece en estado enviada
    // Usado por los escenarios de validación (sin comentario / sin tipo)
    // =========================================================================

    #[Then('la solicitud se mantiene en estado enviada')]
    public function laSolicitudPermanenceEnEstadoEnviada(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que la operación fallara pero el handler completó sin error'
        );

        $persistida = $this->solicitudRepo->buscarPorId($this->solicitudExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::Enviada),
            "Se esperaba que la solicitud permaneciera en estado 'enviada', se obtuvo: {$persistida->estado()->value}"
        );
    }
}
