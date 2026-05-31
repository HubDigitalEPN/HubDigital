<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios\ActualizarConfiguracionGlobalRecordatoriosHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios\ActualizarConfiguracionGlobalRecordatoriosInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico\ActualizarRecordatoriosPrestamoEspecificoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico\ActualizarRecordatoriosPrestamoEspecificoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo\AprobarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo\AprobarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios\DefinirConfiguracionGlobalRecordatoriosHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios\DefinirConfiguracionGlobalRecordatoriosInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo\IniciarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo\IniciarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryConfiguracionGlobalRecordatoriosRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryRecordatorioDevolucionRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudPrestamoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: definicion_recordatorios_devolucion.feature
 * Capability: AdministracionCuratorialSolicitudesPrestamos
 */
final class DefinicionRecordatoriosDevolucionContext extends BaseContext
{
    // ── Repositorios in-memory ────────────────────────────────────────────────

    private InMemoryConfiguracionGlobalRecordatoriosRepository $configRepo;

    private InMemoryRecordatorioDevolucionRepository $recordatorioRepo;

    private InMemoryPrestamoRepository $prestamoRepo;

    private InMemoryActaPrestamoRepository $actaRepo;

    private InMemorySolicitudPrestamoRepository $solicitudRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private DefinirConfiguracionGlobalRecordatoriosHandler $definirConfigHandler;

    private ActualizarConfiguracionGlobalRecordatoriosHandler $actualizarConfigHandler;

    private ActualizarRecordatoriosPrestamoEspecificoHandler $actualizarPrestHandler;

    private IniciarPrestamoHandler $iniciarPrestamoHandler;

    private AprobarProrrogaPrestamoHandler $aprobarProrrogaHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?ConfiguracionGlobalRecordatorios $configuracionExistente = null;

    private ?Prestamo $prestamoExistente = null;

    private ?ActaPrestamo $actaParaActivacion = null;

    private ?SolicitudPrestamo $solicitudParaActivacion = null;

    private ?DateTimeImmutable $nuevaFechaFinProrrogada = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Datos canónicos ───────────────────────────────────────────────────────

    private string $curadorId = 'cur-001';

    private string $investigadorId = 'inv-001';

    /** @var list<int> */
    private array $diasAntesDefault = [30, 15, 7, 1];

    /** @var list<int> */
    private array $diasAntesActualizados = [60, 30, 14, 3];

    /** @var list<int> */
    private array $diasAntesEspecifico = [60, 14, 2];

    // ── Constructor ───────────────────────────────────────────────────────────

    public function __construct()
    {
        self::bootApp();

        $this->configRepo = new InMemoryConfiguracionGlobalRecordatoriosRepository;
        $this->recordatorioRepo = new InMemoryRecordatorioDevolucionRepository;
        $this->prestamoRepo = new InMemoryPrestamoRepository;
        $this->actaRepo = new InMemoryActaPrestamoRepository;
        $this->solicitudRepo = new InMemorySolicitudPrestamoRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;

        self::$app->instance(ConfiguracionGlobalRecordatoriosRepositoryInterface::class, $this->configRepo);
        self::$app->instance(RecordatorioDevolucionRepositoryInterface::class, $this->recordatorioRepo);
        self::$app->instance(PrestamoRepositoryInterface::class, $this->prestamoRepo);
        self::$app->instance(ActaPrestamoRepositoryInterface::class, $this->actaRepo);
        self::$app->instance(SolicitudPrestamoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);

        $this->definirConfigHandler = $this->make(DefinirConfiguracionGlobalRecordatoriosHandler::class);
        $this->actualizarConfigHandler = $this->make(ActualizarConfiguracionGlobalRecordatoriosHandler::class);
        $this->actualizarPrestHandler = $this->make(ActualizarRecordatoriosPrestamoEspecificoHandler::class);
        $this->iniciarPrestamoHandler = $this->make(IniciarPrestamoHandler::class);
        $this->aprobarProrrogaHandler = $this->make(AprobarProrrogaPrestamoHandler::class);
    }

    // ── Helpers de fixture ────────────────────────────────────────────────────

    /**
     * @param  list<int>  $dias
     */
    private function sembrarConfiguracionGlobal(array $dias = [30, 15, 7, 1]): ConfiguracionGlobalRecordatorios
    {
        $configuracion = ConfiguracionGlobalRecordatorios::definir(
            id: $this->configRepo->nextIdentity(),
            curadorId: $this->curadorId,
            diasAntes: $dias,
        );

        $this->configRepo->guardar($configuracion);
        $this->configuracionExistente = $configuracion;

        return $configuracion;
    }

    private function sembrarPrestamoActivo(): Prestamo
    {
        $ahora = new DateTimeImmutable;
        $fechaFin = $ahora->modify('+3 months');

        $prestamo = Prestamo::reconstituir(
            id: $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: ActaPrestamoId::generate(),
            investigadorId: $this->investigadorId,
            estado: EstadoPrestamo::Activo,
            iniciadoEn: $ahora,
            fechaFin: $fechaFin,
        );

        $this->prestamoRepo->guardar($prestamo);
        $this->prestamoExistente = $prestamo;

        return $prestamo;
    }

    /**
     * @param  list<int>  $dias
     */
    private function sembrarRecordatoriosParaPrestamo(Prestamo $prestamo, array $dias = [30, 15, 7, 1]): void
    {
        $fechaFin = $prestamo->fechaFin();

        foreach ($dias as $diasAntes) {
            $recordatorio = RecordatorioDevolucion::programar(
                id: $this->recordatorioRepo->nextIdentity(),
                prestamoId: $prestamo->id(),
                fechaProgramada: $fechaFin->modify("-{$diasAntes} days"),
                diasAntesVencimiento: $diasAntes,
            );
            $this->recordatorioRepo->guardar($recordatorio);
        }
    }

    // =========================================================================
    // ESCENARIO: Definir configuración global de recordatorios
    // =========================================================================

    #[Given('que no existe una configuración global de recordatorios definida')]
    public function queNoExisteUnaConfiguracionGlobalDeRecordatoriosDefinida(): void
    {
        $configuracionActual = $this->configRepo->obtenerUnica();

        Assert::assertNull(
            $configuracionActual,
            'Se esperaba que no existiera ninguna configuración global de recordatorios, pero ya hay una definida'
        );
    }

    #[When('el curador registra la configuración global de recordatorios')]
    public function elCuradorRegistraLaConfiguracionGlobalDeRecordatorios(): void
    {
        try {
            $this->ultimaRespuesta = $this->definirConfigHandler->handle(
                new DefinirConfiguracionGlobalRecordatoriosInput(
                    curadorId: $this->curadorId,
                    diasAntes: $this->diasAntesDefault,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('todos los préstamos quedan sujetos a la configuración definida')]
    public function todosLosPrestamosQuedanSujetosALaConfiguracionDefinida(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);

        $configuracionPersistida = $this->configRepo->obtenerUnica();
        Assert::assertNotNull($configuracionPersistida, 'Se esperaba que la configuración global quedara persistida');
        Assert::assertSame(
            $this->diasAntesDefault,
            $configuracionPersistida->diasAntes(),
            'Los días de cadencia persistidos no coinciden con los registrados'
        );
    }

    // =========================================================================
    // ESCENARIO: Modificar configuración global de recordatorios
    // =========================================================================

    #[Given('que existe una configuración global de recordatorios previamente definida')]
    public function queExisteUnaConfiguracionGlobalDeRecordatoriosPreviamenteDefinida(): void
    {
        $this->sembrarConfiguracionGlobal($this->diasAntesDefault);

        $configuracionPersistida = $this->configRepo->obtenerUnica();
        Assert::assertNotNull(
            $configuracionPersistida,
            'Se esperaba que la configuración global quedara sembrada correctamente'
        );
        Assert::assertNotSame(
            $this->diasAntesActualizados,
            $configuracionPersistida->diasAntes(),
            'Los días actualizados deben ser distintos a los actuales para validar la actualización'
        );
    }

    #[When('el curador actualiza la configuración global de recordatorios')]
    public function elCuradorActualizaLaConfiguracionGlobalDeRecordatorios(): void
    {
        Assert::assertNotNull($this->configuracionExistente);
        Assert::assertNotSame(
            $this->diasAntesActualizados,
            $this->configuracionExistente->diasAntes(),
            'Los nuevos días deben diferir de los actuales antes de actualizar'
        );

        try {
            $this->ultimaRespuesta = $this->actualizarConfigHandler->handle(
                new ActualizarConfiguracionGlobalRecordatoriosInput(
                    configuracionId: (string) $this->configuracionExistente->id(),
                    curadorId: $this->curadorId,
                    diasAntes: $this->diasAntesActualizados,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('todos los préstamos quedan sujetos a la configuración actualizada')]
    public function todosLosPrestamosQuedanSujetosALaConfiguracionActualizada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);

        $configuracionPersistida = $this->configRepo->obtenerUnica();
        Assert::assertNotNull($configuracionPersistida);
        Assert::assertSame(
            $this->diasAntesActualizados,
            $configuracionPersistida->diasAntes(),
            'Los días de cadencia actualizados no coinciden con los persistidos'
        );
    }

    // =========================================================================
    // ESCENARIO: Modificar recordatorios de un préstamo específico
    // =========================================================================

    #[Given('que existe un préstamo activo con recordatorios configurados')]
    public function queExisteUnPrestamoActivoConRecordatoriosConfigurados(): void
    {
        $prestamo = $this->sembrarPrestamoActivo();
        $this->sembrarRecordatoriosParaPrestamo($prestamo, $this->diasAntesDefault);

        Assert::assertTrue(
            $prestamo->estado()->equals(EstadoPrestamo::Activo),
            "Se esperaba estado 'activo' en el préstamo sembrado"
        );

        $recordatoriosExistentes = $this->recordatorioRepo->listarPorPrestamo($prestamo->id());
        Assert::assertCount(
            count($this->diasAntesDefault),
            $recordatoriosExistentes,
            'La cantidad de recordatorios sembrados no coincide con la cadencia por defecto'
        );
        Assert::assertNotSame(
            $this->diasAntesEspecifico,
            $this->diasAntesDefault,
            'Los días específicos a aplicar deben ser distintos a los ya configurados'
        );
    }

    #[When('el curador actualiza la configuración de recordatorios del préstamo')]
    public function elCuradorActualizaLaConfiguracionDeRecordatoriosDelPrestamo(): void
    {
        Assert::assertNotNull($this->prestamoExistente);

        $recordatoriosActuales = $this->recordatorioRepo->listarPorPrestamo($this->prestamoExistente->id());
        $diasActuales = array_map(fn ($r) => $r->diasAntesVencimiento(), $recordatoriosActuales);
        sort($diasActuales);
        $diasEspecificoOrdenado = $this->diasAntesEspecifico;
        sort($diasEspecificoOrdenado);

        Assert::assertNotSame(
            $diasEspecificoOrdenado,
            $diasActuales,
            'Los días específicos a aplicar deben diferir de los días actualmente configurados en el préstamo'
        );

        try {
            $this->ultimaRespuesta = $this->actualizarPrestHandler->handle(
                new ActualizarRecordatoriosPrestamoEspecificoInput(
                    prestamoId: (string) $this->prestamoExistente->id(),
                    curadorId: $this->curadorId,
                    diasAntes: $this->diasAntesEspecifico,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el préstamo queda configurado con los recordatorios actualizados')]
    public function elPrestamoQuedaConfiguradoConLosRecordatoriosActualizados(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);

        $recordatoriosActualizados = $this->recordatorioRepo->listarPorPrestamo($this->prestamoExistente->id());
        Assert::assertCount(
            count($this->diasAntesEspecifico),
            $recordatoriosActualizados,
            'La cantidad de recordatorios no coincide con los días específicos configurados'
        );

        $diasPersistidos = array_map(fn ($r) => $r->diasAntesVencimiento(), $recordatoriosActualizados);
        sort($diasPersistidos);
        $diasEsperados = $this->diasAntesEspecifico;
        sort($diasEsperados);

        Assert::assertSame(
            $diasEsperados,
            $diasPersistidos,
            'Los días de los recordatorios actualizados no coinciden con los solicitados'
        );
    }

    // =========================================================================
    // ESCENARIO: Generar recordatorios automáticamente al activar un préstamo
    // =========================================================================

    #[Given('que existe un acta de préstamo lista para ser iniciada')]
    public function queExisteUnPrestamoEnEstadoActivo(): void
    {
        // Sembramos ActaPrestamo + SolicitudPrestamo listos para IniciarPrestamoHandler
        $solicitud = SolicitudPrestamo::reconstituir(
            id: $this->solicitudRepo->nextIdentity(),
            numeroSolicitud: NumeroSolicitud::generate(),
            investigadorId: $this->investigadorId,
            estado: EstadoSolicitud::Aprobada,
            tituloEstudio: 'Estudio de musgos ecuatorianos',
            institucionAdscripcion: 'Escuela Politécnica Nacional',
            lineaInvestigacion: 'Briología',
            propositoPrestamo: 'Revisión taxonómica de Bryophyta',
            duracionPropuestaMeses: 3,
            justificacionExtendida: null,
            comentarioCurador: null,
            items: [],
            enviadaEn: new DateTimeImmutable('-1 month'),
            resueltaEn: new DateTimeImmutable('-2 weeks'),
            resueltaPor: $this->curadorId,
        );
        $this->solicitudRepo->guardar($solicitud);

        $ahora = new DateTimeImmutable;
        $fechaFin = $ahora->modify('+3 months');

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            numeroPrestamo: NumeroPrestamo::generate(),
            solicitudPrestamoId: $solicitud->id(),
            tipoPrestamo: TipoPrestamo::Temporal,
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: 'actas/escenario-activacion.pdf',
        );
        $this->actaRepo->guardar($acta);

        $this->actaParaActivacion = $acta;
        $this->solicitudParaActivacion = $solicitud;

        Assert::assertNotNull($this->actaRepo->buscarPorId($acta->id()));
        Assert::assertNotNull($this->solicitudRepo->buscarPorId($solicitud->id()));
        Assert::assertNotNull($acta->fechaFin());
        Assert::assertTrue($acta->fechaFin() > $ahora, 'La fecha de fin del acta debe ser posterior a la fecha de inicio');
    }

    #[Given('existe una configuración global de recordatorios definida')]
    public function existeUnaConfiguracionGlobalDeRecordatoriosDefinida(): void
    {
        $this->sembrarConfiguracionGlobal($this->diasAntesDefault);

        Assert::assertNotNull(
            $this->configRepo->obtenerUnica(),
            'Se esperaba que la configuración global quedara sembrada'
        );
    }

    #[When('el préstamo es activado')]
    public function elPrestamoEsActivado(): void
    {
        Assert::assertNotNull($this->actaParaActivacion, 'Se requiere un acta sembrada para activar el préstamo');
        Assert::assertNotNull($this->solicitudParaActivacion, 'Se requiere una solicitud sembrada para activar el préstamo');

        try {
            $this->ultimaRespuesta = $this->iniciarPrestamoHandler->handle(
                new IniciarPrestamoInput(
                    actaPrestamoId: (string) $this->actaParaActivacion->id(),
                    solicitudId: (string) $this->solicitudParaActivacion->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se generan los recordatorios de devolución según la configuración global')]
    public function seGeneranLosRecordatoriosDeDevolucionSegunLaConfiguracionGlobal(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);

        $prestamoId = PrestamoId::fromString($this->ultimaRespuesta->prestamoId);
        $recordatorios = $this->recordatorioRepo->listarPorPrestamo($prestamoId);

        Assert::assertCount(
            count($this->diasAntesDefault),
            $recordatorios,
            sprintf(
                'Se esperaban %d recordatorios según la configuración global, se encontraron %d',
                count($this->diasAntesDefault),
                count($recordatorios)
            )
        );

        $fechaFin = $this->actaParaActivacion->fechaFin();
        $diasPersistidos = array_map(fn ($r) => $r->diasAntesVencimiento(), $recordatorios);

        foreach ($recordatorios as $recordatorio) {
            $diasAntes = $recordatorio->diasAntesVencimiento();
            $fechaEsperada = $fechaFin->modify("-{$diasAntes} days");

            Assert::assertEquals(
                $fechaEsperada->format('Y-m-d'),
                $recordatorio->fechaProgramada()->format('Y-m-d'),
                "La fecha del recordatorio de {$diasAntes} días no corresponde a la fecha de fin del préstamo"
            );
            Assert::assertContains(
                $diasAntes,
                $this->diasAntesDefault,
                "El recordatorio con {$diasAntes} días no pertenece a la cadencia global configurada"
            );
        }

        Assert::assertEmpty(
            array_diff($this->diasAntesDefault, $diasPersistidos),
            'Faltan recordatorios para alguno de los días de la cadencia global'
        );
    }

    // =========================================================================
    // ESCENARIO: Regenerar recordatorios al aprobar una prórroga
    // =========================================================================

    #[Given('que existe un préstamo en estado activo con recordatorios configurados')]
    public function queExisteUnPrestamoEnEstadoActivoConRecordatoriosConfigurados(): void
    {
        $prestamo = $this->sembrarPrestamoActivo();
        $this->sembrarRecordatoriosParaPrestamo($prestamo, $this->diasAntesDefault);

        Assert::assertTrue(
            $prestamo->estado()->equals(EstadoPrestamo::Activo),
            "Se esperaba estado 'activo' en el préstamo sembrado"
        );

        $recordatoriosExistentes = $this->recordatorioRepo->listarPorPrestamo($prestamo->id());
        Assert::assertCount(
            count($this->diasAntesDefault),
            $recordatoriosExistentes,
            'Los recordatorios sembrados no coinciden con la cadencia por defecto'
        );
    }

    #[When('la prórroga del préstamo es aprobada con una nueva fecha de vencimiento')]
    public function laProrrogaDelPrestamoEsAprobadaConUnaNuevaFechaDeVencimiento(): void
    {
        Assert::assertNotNull($this->prestamoExistente);

        $nuevaFechaFin = new DateTimeImmutable('+6 months');

        Assert::assertGreaterThan(
            $this->prestamoExistente->fechaFin()->getTimestamp(),
            $nuevaFechaFin->getTimestamp(),
            'La nueva fecha de vencimiento de la prórroga debe ser posterior a la fecha original'
        );

        $this->nuevaFechaFinProrrogada = $nuevaFechaFin;

        try {
            $this->ultimaRespuesta = $this->aprobarProrrogaHandler->handle(
                new AprobarProrrogaPrestamoInput(
                    prestamoId: (string) $this->prestamoExistente->id(),
                    curadorId: $this->curadorId,
                    nuevaFechaFin: $nuevaFechaFin->format(\DateTimeInterface::ATOM),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('los recordatorios del préstamo se regeneran según la nueva fecha')]
    public function losRecordatoriosDelPrestamoSeRegeneranSegunLaNuevaFecha(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertNotNull($this->nuevaFechaFinProrrogada);

        $recordatoriosRegenerados = $this->recordatorioRepo->listarPorPrestamo($this->prestamoExistente->id());

        Assert::assertCount(
            count($this->diasAntesDefault),
            $recordatoriosRegenerados,
            'La cantidad de recordatorios regenerados debe coincidir con la cadencia original'
        );

        $nuevaFechaFin = $this->nuevaFechaFinProrrogada;

        foreach ($recordatoriosRegenerados as $recordatorio) {
            $diasAntes = $recordatorio->diasAntesVencimiento();
            $fechaEsperada = $nuevaFechaFin->modify("-{$diasAntes} days");

            Assert::assertEquals(
                $fechaEsperada->format('Y-m-d'),
                $recordatorio->fechaProgramada()->format('Y-m-d'),
                "La fecha del recordatorio de {$diasAntes} días no corresponde a la nueva fecha de vencimiento prorrogada"
            );
            Assert::assertNotEquals(
                $this->prestamoExistente->fechaFin()->modify("-{$diasAntes} days")->format('Y-m-d'),
                $recordatorio->fechaProgramada()->format('Y-m-d'),
                "El recordatorio de {$diasAntes} días aún usa la fecha original — no fue regenerado correctamente"
            );
        }
    }
}
