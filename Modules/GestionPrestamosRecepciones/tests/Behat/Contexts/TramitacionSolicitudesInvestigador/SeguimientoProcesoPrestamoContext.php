<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaPrestamo\ConsultarActaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaPrestamo\ConsultarActaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudPrestamo\ConsultarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudPrestamo\ConsultarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\InMemoryHistorialAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudPrestamoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: seguimiento_proceso_prestamo.feature
 * Capability: TramitacionSolicitudesInvestigador
 */
final class SeguimientoProcesoPrestamoContext extends BaseContext
{
    // ── Repositorios in-memory ────────────────────────────────────────────────

    private InMemorySolicitudPrestamoRepository $solicitudRepo;

    private InMemoryActaPrestamoRepository $actaRepo;

    private InMemoryPrestamoRepository $prestamoRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    private InMemoryHistorialAdapter $historialAdapter;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private ConsultarSolicitudPrestamoHandler $consultarSolicitudHandler;

    private ConsultarActaPrestamoHandler $consultarActaHandler;

    private ConsultarPrestamoHandler $consultarPrestamoHandler;

    private ConsultarHistorialSolicitudHandler $consultarHistorialSolicitudHandler;

    private ConsultarHistorialPrestamoHandler $consultarHistorialPrestamoHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?SolicitudPrestamo $solicitudExistente = null;

    private ?ActaPrestamo $actaExistente = null;

    private ?Prestamo $prestamoExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $investigadorId = 'inv-001';

    private string $curadorId = 'cur-001';

    private string $usuarioId = 'usr-001';

    private array $datosSolicitudBase = [
        'titulo_estudio' => 'Revisión taxonómica del género Morpho en Ecuador',
        'institucion_adscripcion' => 'Universidad Central del Ecuador',
        'linea_investigacion' => 'Entomología sistemática',
        'proposito_prestamo' => 'Estudio comparativo de morfología alar',
        'duracion_propuesta_meses' => 3,
        'items' => [
            ['especimen_codigo_externo' => 'ESP-001', 'cantidad_solicitada' => 2],
        ],
    ];

    // ── Constructor ───────────────────────────────────────────────────────────

    public function __construct()
    {
        self::bootApp();

        $this->solicitudRepo = new InMemorySolicitudPrestamoRepository;
        $this->actaRepo = new InMemoryActaPrestamoRepository;
        $this->prestamoRepo = new InMemoryPrestamoRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;
        $this->historialAdapter = new InMemoryHistorialAdapter;

        self::$app->instance(SolicitudPrestamoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(ActaPrestamoRepositoryInterface::class, $this->actaRepo);
        self::$app->instance(PrestamoRepositoryInterface::class, $this->prestamoRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(HistorialPort::class, $this->historialAdapter);

        $this->consultarSolicitudHandler = $this->make(ConsultarSolicitudPrestamoHandler::class);
        $this->consultarActaHandler = $this->make(ConsultarActaPrestamoHandler::class);
        $this->consultarPrestamoHandler = $this->make(ConsultarPrestamoHandler::class);
        $this->consultarHistorialSolicitudHandler = $this->make(ConsultarHistorialSolicitudHandler::class);
        $this->consultarHistorialPrestamoHandler = $this->make(ConsultarHistorialPrestamoHandler::class);
    }

    // ── Helpers de fixture ────────────────────────────────────────────────────

    private function sembrarSolicitudBase(): SolicitudPrestamo
    {
        $items = array_map(
            fn (array $item) => ItemPrestamo::crear(
                id: ItemPrestamoId::generate(),
                especimenCodigoExterno: $item['especimen_codigo_externo'],
                cantidadSolicitada: $item['cantidad_solicitada'],
            ),
            $this->datosSolicitudBase['items'],
        );

        $solicitud = SolicitudPrestamo::crear(
            id: $this->solicitudRepo->nextIdentity(),
            numeroSolicitud: NumeroSolicitud::generate(),
            investigadorId: $this->investigadorId,
            alcancePrestamo: AlcancePrestamo::Nacional,
            tituloEstudio: $this->datosSolicitudBase['titulo_estudio'],
            institucionAdscripcion: $this->datosSolicitudBase['institucion_adscripcion'],
            lineaInvestigacion: $this->datosSolicitudBase['linea_investigacion'],
            propositoPrestamo: $this->datosSolicitudBase['proposito_prestamo'],
            duracionPropuestaMeses: $this->datosSolicitudBase['duracion_propuesta_meses'],
            items: $items,
        );

        $this->solicitudRepo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    private function sembrarActaEnEstado(EstadoActa $estado): ActaPrestamo
    {
        $solicitud = $this->sembrarSolicitudBase();
        $solicitud->enviar();
        $solicitud->aprobar(curadorId: $this->curadorId);
        $this->solicitudRepo->guardar($solicitud);

        $ahora = new DateTimeImmutable;
        $fechaFin = $ahora->modify('+3 months');
        $pdfRuta = 'actas/'.(string) $solicitud->id().'.pdf';

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            numeroPrestamo: NumeroPrestamo::generate(),
            solicitudPrestamoId: $solicitud->id(),
            tipoPrestamo: TipoPrestamo::Temporal,
            alcancePrestamo: AlcancePrestamo::Nacional,
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
        );

        if ($estado === EstadoActa::PendienteFirma || $estado === EstadoActa::PendienteValidacion || $estado === EstadoActa::Validada) {
            $acta->marcarEnviada($this->investigadorId);
        }
        if ($estado === EstadoActa::PendienteValidacion || $estado === EstadoActa::Validada) {
            $acta->subirFirma('actas/firmadas/'.(string) $solicitud->id().'-firmada.pdf');
        }
        if ($estado === EstadoActa::Validada) {
            $acta->validar($this->curadorId);
        }

        $this->actaRepo->guardar($acta);
        $this->actaExistente = $acta;

        return $acta;
    }

    private function sembrarPrestamoEnEstado(EstadoPrestamo $estado): Prestamo
    {
        $ahora = new DateTimeImmutable;

        $prestamo = Prestamo::reconstituir(
            id: $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: ActaPrestamoId::generate(),
            investigadorId: $this->investigadorId,
            estado: $estado,
            iniciadoEn: $ahora,
            fechaFin: $ahora->modify('+3 months'),
        );

        $this->prestamoRepo->guardar($prestamo);
        $this->prestamoExistente = $prestamo;

        return $prestamo;
    }

    private function mapearEstadoActa(string $estadoHumano): EstadoActa
    {
        return match ($estadoHumano) {
            'pendiente de envío' => EstadoActa::PendienteEnvio,
            'pendiente de firma' => EstadoActa::PendienteFirma,
            'pendiente de validación' => EstadoActa::PendienteValidacion,
            'validada' => EstadoActa::Validada,
            default => throw new \InvalidArgumentException("Estado de acta desconocido: '{$estadoHumano}'"),
        };
    }

    private function mapearEstadoPrestamo(string $estadoHumano): EstadoPrestamo
    {
        return match ($estadoHumano) {
            'activo' => EstadoPrestamo::Activo,
            'prórroga solicitada' => EstadoPrestamo::ProrrogaSolicitada,
            'vencido' => EstadoPrestamo::Vencido,
            'en revisión' => EstadoPrestamo::EnRevision,
            'cerrado' => EstadoPrestamo::Cerrado,
            'cerrado con observación' => EstadoPrestamo::CerradoConObservacion,
            default => throw new \InvalidArgumentException("Estado de préstamo desconocido: '{$estadoHumano}'"),
        };
    }

    // =========================================================================
    // ESCENARIO: Conocer el estado de mi solicitud en borrador (@investigador)
    // =========================================================================

    #[Given('que existe una solicitud del investigador en estado borrador')]
    public function queExisteUnaSolicitudDelInvestigadorEnEstadoBorrador(): void
    {
        $solicitud = $this->sembrarSolicitudBase();
        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());

        Assert::assertNotNull($persistida, 'La solicitud no fue persistida en el repositorio');
        Assert::assertSame(
            $this->investigadorId,
            $persistida->investigadorId(),
            'La solicitud debe pertenecer al investigador esperado'
        );
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::Borrador),
            "Se esperaba estado 'borrador', se obtuvo: {$persistida->estado()->value}"
        );
    }

    #[When('el investigador consulta la información de la solicitud')]
    public function elInvestigadorConsultaLaInformacionDeLaSolicitud(): void
    {
        Assert::assertNotNull($this->solicitudExistente, 'No hay solicitud existente para consultar');

        try {
            $this->ultimaRespuesta = $this->consultarSolicitudHandler->handle(
                new ConsultarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    usuarioId: $this->investigadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Conocer el estado de una solicitud (@investigador @curador)
    // =========================================================================

    #[Given('/^que existe una solicitud en estado (.+)$/u')]
    public function queExisteUnaSolicitudEnEstado(string $estado): void
    {
        $solicitud = $this->sembrarSolicitudBase();
        $estadoEnum = EstadoSolicitud::from($estado);

        if ($estadoEnum !== EstadoSolicitud::Borrador) {
            $solicitud->enviar();
        }
        if ($estadoEnum === EstadoSolicitud::Observada) {
            $solicitud->observar(
                curadorId: $this->curadorId,
                observacion: 'Observación de prueba para el escenario de seguimiento',
            );
        }
        if ($estadoEnum === EstadoSolicitud::Aprobada) {
            $solicitud->aprobar(curadorId: $this->curadorId);
        }
        if ($estadoEnum === EstadoSolicitud::Rechazada) {
            $solicitud->rechazar(curadorId: $this->curadorId, motivo: 'Motivo de rechazo de prueba');
        }

        $this->solicitudRepo->guardar($solicitud);

        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals($estadoEnum),
            "Se esperaba estado '{$estado}', se obtuvo: {$persistida->estado()->value}"
        );
    }

    #[When('el usuario consulta la información de la solicitud')]
    public function elUsuarioConsultaLaInformacionDeLaSolicitud(): void
    {
        Assert::assertNotNull($this->solicitudExistente, 'No hay solicitud existente para consultar');

        try {
            $this->ultimaRespuesta = $this->consultarSolicitudHandler->handle(
                new ConsultarSolicitudPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    usuarioId: $this->usuarioId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('/^la solicitud es retornada con estado (.+)$/u')]
    public function laSolicitudEsRetornadaConEstado(string $estado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitud::from($estado)),
            "Se esperaba estado '{$estado}', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Conocer el estado del acta de préstamo
    // =========================================================================

    #[Given('/^que existe un acta en estado (.+)$/u')]
    public function queExisteUnActaEnEstado(string $estadoHumano): void
    {
        $estadoEnum = $this->mapearEstadoActa($estadoHumano);
        $acta = $this->sembrarActaEnEstado($estadoEnum);

        $persistida = $this->actaRepo->buscarPorId($acta->id());
        Assert::assertNotNull($persistida, 'El acta no fue persistida en el repositorio');
        Assert::assertTrue(
            $persistida->estado()->equals($estadoEnum),
            "Se esperaba estado del acta '{$estadoHumano}', se obtuvo: {$persistida->estado()->value}"
        );
    }

    #[When('el usuario consulta la información del acta')]
    public function elUsuarioConsultaLaInformacionDelActa(): void
    {
        Assert::assertNotNull($this->actaExistente, 'No hay acta existente para consultar');

        try {
            $this->ultimaRespuesta = $this->consultarActaHandler->handle(
                new ConsultarActaPrestamoInput(
                    actaId: (string) $this->actaExistente->id(),
                    usuarioId: $this->usuarioId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('/^el acta es retornada con estado (.+)$/u')]
    public function elActaEsRetornadaConEstado(string $estadoHumano): void
    {
        $estadoEnum = $this->mapearEstadoActa($estadoHumano);

        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals($estadoEnum),
            "Se esperaba estado del acta '{$estadoHumano}', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Conocer el estado de un préstamo
    // =========================================================================

    #[Given('/^que existe un préstamo en estado ([^\s]+)$/u')]
    public function queExisteUnPrestamoEnEstado(string $estadoHumano): void
    {
        $estadoEnum = $this->mapearEstadoPrestamo($estadoHumano);
        $prestamo = $this->sembrarPrestamoEnEstado($estadoEnum);

        $persistido = $this->prestamoRepo->buscarPorId($prestamo->id());
        Assert::assertNotNull($persistido, 'El préstamo no fue persistido en el repositorio');
        Assert::assertTrue(
            $persistido->estado()->equals($estadoEnum),
            "Se esperaba estado del préstamo '{$estadoHumano}', se obtuvo: {$persistido->estado()->value}"
        );
    }

    #[When('el usuario consulta la información del préstamo')]
    public function elUsuarioConsultaLaInformacionDelPrestamo(): void
    {
        Assert::assertNotNull($this->prestamoExistente, 'No hay préstamo existente para consultar');

        try {
            $this->ultimaRespuesta = $this->consultarPrestamoHandler->handle(
                new ConsultarPrestamoInput(
                    prestamoId: (string) $this->prestamoExistente->id(),
                    usuarioId: $this->usuarioId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('/^el préstamo es retornado con estado (.+)$/u')]
    public function elPrestamoEsRetornadoConEstado(string $estadoHumano): void
    {
        $estadoEnum = $this->mapearEstadoPrestamo($estadoHumano);

        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals($estadoEnum),
            "Se esperaba estado del préstamo '{$estadoHumano}', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Consultar la trazabilidad de una solicitud
    // =========================================================================

    #[Given('/^que existe una solicitud con (.+)$/u')]
    public function queExisteUnaSolicitudCon(string $condicion): void
    {
        $solicitud = $this->sembrarSolicitudBase();

        if ($condicion === 'eventos registrados') {
            $solicitud->enviar();
            $solicitud->observar(
                curadorId: $this->curadorId,
                observacion: 'Observación para generar eventos de trazabilidad',
            );
            $this->solicitudRepo->guardar($solicitud);

            foreach ($solicitud->pullEvents() as $evento) {
                $this->fakePublisher->publish($evento);
                $this->historialAdapter->registrar($evento);
            }

            Assert::assertNotEmpty(
                $this->fakePublisher->publishedEvents(),
                "Se esperaba al menos un evento publicado para la condición 'eventos registrados'"
            );
        }

        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud no fue persistida en el repositorio');
    }

    #[When('el usuario solicita el historial de la solicitud')]
    public function elUsuarioSolicitaElHistorialDeLaSolicitud(): void
    {
        Assert::assertNotNull($this->solicitudExistente, 'No hay solicitud existente para consultar su historial');

        try {
            $this->ultimaRespuesta = $this->consultarHistorialSolicitudHandler->handle(
                new ConsultarHistorialSolicitudInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    usuarioId: $this->usuarioId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('/^el historial es retornado (.+)$/u')]
    public function elHistorialEsRetornado(string $resultado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó respuesta');

        if ($resultado === 'con los eventos en orden cronológico') {
            Assert::assertNotEmpty(
                $this->ultimaRespuesta->eventos,
                'Se esperaba que el historial contuviera eventos'
            );

            $fechas = array_map(
                fn (object $e) => $e->ocurridoEn,
                $this->ultimaRespuesta->eventos,
            );

            $fechasOrdenadas = $fechas;
            usort($fechasOrdenadas, fn ($a, $b) => $a <=> $b);

            Assert::assertSame(
                $fechas,
                $fechasOrdenadas,
                'Los eventos del historial no están en orden cronológico ascendente'
            );
        }

        if ($resultado === 'vacío') {
            Assert::assertEmpty(
                $this->ultimaRespuesta->eventos,
                'Se esperaba que el historial estuviera vacío pero contiene eventos'
            );
        }
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Consultar la trazabilidad de un préstamo
    // =========================================================================

    #[Given('/^que existe un préstamo con (.+)$/u')]
    public function queExisteUnPrestamoCon(string $condicion): void
    {
        if ($condicion === 'eventos registrados') {
            $ahora = new DateTimeImmutable;
            $prestamo = Prestamo::iniciar(
                id: $this->prestamoRepo->nextIdentity(),
                actaPrestamoId: ActaPrestamoId::generate(),
                investigadorId: $this->investigadorId,
                alcancePrestamo: AlcancePrestamo::Nacional,
                iniciadoEn: $ahora,
                fechaFin: $ahora->modify('+3 months'),
            );
            $this->prestamoRepo->guardar($prestamo);
            $this->prestamoExistente = $prestamo;

            foreach ($prestamo->pullEvents() as $evento) {
                $this->fakePublisher->publish($evento);
                $this->historialAdapter->registrar($evento);
            }

            Assert::assertNotEmpty(
                $this->fakePublisher->publishedEvents(),
                "Se esperaba al menos un evento publicado para la condición 'eventos registrados'"
            );
        } else {
            $this->sembrarPrestamoEnEstado(EstadoPrestamo::Activo);
        }

        $persistido = $this->prestamoRepo->buscarPorId($this->prestamoExistente->id());
        Assert::assertNotNull($persistido, 'El préstamo no fue persistido en el repositorio');
    }

    #[When('el usuario solicita el historial del préstamo')]
    public function elUsuarioSolicitaElHistorialDelPrestamo(): void
    {
        Assert::assertNotNull($this->prestamoExistente, 'No hay préstamo existente para consultar su historial');

        try {
            $this->ultimaRespuesta = $this->consultarHistorialPrestamoHandler->handle(
                new ConsultarHistorialPrestamoInput(
                    prestamoId: (string) $this->prestamoExistente->id(),
                    usuarioId: $this->usuarioId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // @Then "el historial es retornado …" → reutiliza elHistorialEsRetornado() de arriba
}
