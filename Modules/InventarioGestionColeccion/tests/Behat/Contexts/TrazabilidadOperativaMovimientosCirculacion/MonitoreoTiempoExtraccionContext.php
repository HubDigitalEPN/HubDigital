<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaOutput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VerificarTiemposExtraccion\VerificarTiemposExtraccionHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VerificarTiemposExtraccion\VerificarTiemposExtraccionInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VerificarTiemposExtraccion\VerificarTiemposExtraccionOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Horario;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\HorarioId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes\FakeContextoEjecucionAdapter;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes\FakeEventPublisherAdapter;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes\FakeHorarioValidadorAdapter;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes\PassThroughTransactionManagerAdapter;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryAlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryCajaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryGabineteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryHorarioRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryNotificacionRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryRanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayRepository;
use PHPUnit\Framework\Assert;

final class MonitoreoTiempoExtraccionContext extends BaseContext
{
    private VerificarTiemposExtraccionHandler $verificarHandler;

    private RegistrarIngresoCajaHandler $ingresoHandler;

    private InMemoryGabineteRepository $gabineteRepo;

    private InMemoryRanuraGabineteRepository $ranuraRepo;

    private InMemoryCajaRepository $cajaRepo;

    private InMemoryUbicacionCajaRepository $ubicacionRepo;

    private InMemoryAlertaUbicacionRepository $alertaRepo;

    private InMemoryNotificacionRepository $notificacionRepo;

    private ?CajaId $cajaId = null;

    private ?RanuraId $ranuraOrigenId = null;

    private ?VerificarTiemposExtraccionOutput $ultimaVerificacionOutput = null;

    private ?RegistrarIngresoCajaOutput $ultimaIngresoOutput = null;

    private ?\Throwable $excepcionCapturada = null;

    public function __construct()
    {
        $this->gabineteRepo = new InMemoryGabineteRepository;
        $this->ranuraRepo = new InMemoryRanuraGabineteRepository;
        $this->cajaRepo = new InMemoryCajaRepository;
        $this->ubicacionRepo = new InMemoryUbicacionCajaRepository;
        $this->alertaRepo = new InMemoryAlertaUbicacionRepository;
        $this->notificacionRepo = new InMemoryNotificacionRepository;

        $horarioRepo = new InMemoryHorarioRepository;
        $horarioRepo->guardar(Horario::crear(id: HorarioId::generar(), horaInicio: 8, horaFin: 18));

        $horarioFake = new FakeHorarioValidadorAdapter($horarioRepo);
        $horarioFake->setFueraDeHorario(false);

        self::$app->instance(HorarioValidadorPort::class, $horarioFake);
        self::$app->instance(HorarioRepository::class, $horarioRepo);
        self::$app->instance(EventPublisherPort::class, new FakeEventPublisherAdapter);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(ContextoEjecucionPort::class, new FakeContextoEjecucionAdapter);
        self::$app->instance(GabineteRepository::class, $this->gabineteRepo);
        self::$app->instance(RanuraGabineteRepository::class, $this->ranuraRepo);
        self::$app->instance(CajaRepository::class, $this->cajaRepo);
        self::$app->instance(UbicacionCajaRepository::class, $this->ubicacionRepo);
        self::$app->instance(AlertaUbicacionRepository::class, $this->alertaRepo);
        self::$app->instance(NotificacionRepository::class, $this->notificacionRepo);
        self::$app->instance(EventoCicloIotRepository::class, new InMemoryEventoCicloIotRepository);
        self::$app->instance(UnitTrayRepository::class, new InMemoryUnitTrayRepository);

        $this->verificarHandler = $this->make(VerificarTiemposExtraccionHandler::class);
        $this->ingresoHandler = $this->make(RegistrarIngresoCajaHandler::class);
    }

    // ==========================================
    // ESQUEMA: Evaluación del tiempo de extracción
    // ==========================================

    #[Given('/^que existe una caja fuera de su posición con condición (.+)$/u')]
    public function queExisteUnaCajaFueraDeSuPosicionConCondicion(string $condicion): void
    {
        $retiradaEn = match (true) {
            str_contains($condicion, 'dentro del límite') => new \DateTimeImmutable('-4 hours'),
            str_contains($condicion, 'próxima a superar') => new \DateTimeImmutable('-22 hours'),
            str_contains($condicion, 'superando el límite') => new \DateTimeImmutable('-26 hours'),
            default => throw new \InvalidArgumentException("Condición desconocida: {$condicion}"),
        };

        $ranura = $this->sembrarGabineteConRanura('GAB-EXT-001', 'CAJA-EXT-001');

        // Historial de retiro: una ubicación cerrada cuyo retiro ocurrió en el pasado.
        $ubicacion = UbicacionCaja::registrar(
            id: $this->ubicacionRepo->nextIdentity(),
            cajaId: $this->cajaId,
            ranuraGabineteId: $ranura->id(),
            ingresadaEn: $retiradaEn->modify('-2 hours'),
        );
        $ubicacion->cerrar($retiradaEn);
        $this->ubicacionRepo->guardar($ubicacion);

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja, 'La caja debe estar persistida');
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnTransito),
            'La caja debe estar en tránsito (fuera de su posición)'
        );
    }

    #[When('se verifican los tiempos de extracción')]
    public function seVerificanLosTiemposDeExtraccion(): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja sembrada (@Given)');

        try {
            $this->ultimaVerificacionOutput = $this->verificarHandler->handle(
                new VerificarTiemposExtraccionInput(
                    cajaId: (string) $this->cajaId,
                    limiteDiasHabiles: 1,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el estado permanece "En Tránsito" sin alertas')]
    public function elEstadoPermaneceEnTransitoSinAlertas(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción al verificar tiempos');
        Assert::assertNotNull($this->ultimaVerificacionOutput, 'El handler debe retornar una respuesta');
        Assert::assertFalse(
            $this->ultimaVerificacionOutput->alertaGenerada,
            'No debe generarse alerta dentro del límite'
        );
        Assert::assertFalse(
            $this->ultimaVerificacionOutput->notificacionPreventiva,
            'No debe enviarse notificación preventiva dentro del límite'
        );

        Assert::assertNull(
            $this->alertaRepo->buscarActivaPorCaja($this->cajaId),
            'No debe existir ninguna alerta activa para esta caja'
        );

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnTransito),
            'La caja debe permanecer en estado "en_transito"'
        );
    }

    #[Then('se envía una notificación preventiva al curador responsable')]
    public function seEnviaUnaNotificacionPreventivaAlCuradorResponsable(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción');
        Assert::assertNotNull($this->ultimaVerificacionOutput);
        Assert::assertTrue(
            $this->ultimaVerificacionOutput->notificacionPreventiva,
            'El output debe indicar que se generó notificación preventiva'
        );

        $notificacion = $this->notificacionRepo->buscarUltimaNotificacionPorCaja($this->cajaId);
        Assert::assertNotNull($notificacion, 'Debe haberse registrado una notificación preventiva');
        Assert::assertSame(
            'extraccion_proxima_al_limite',
            $notificacion->tipo(),
            'La notificación debe ser de tipo preventivo'
        );

        Assert::assertNull(
            $this->alertaRepo->buscarActivaPorCaja($this->cajaId),
            'Una notificación preventiva no debe generar todavía una alerta formal'
        );
    }

    #[Then('se genera una alerta de "Extracción Prolongada" y el estado de la caja cambia a "Extracción Prolongada"')]
    public function seGeneraUnaAlertaDeExtraccionProlongada(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción');
        Assert::assertNotNull($this->ultimaVerificacionOutput);
        Assert::assertTrue(
            $this->ultimaVerificacionOutput->alertaGenerada,
            'El output debe indicar que se generó alerta de exceso'
        );

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNotNull($alerta, 'Debe existir una alerta activa');
        Assert::assertTrue(
            $alerta->tipo()->equals(TipoAlerta::ExtraccionProlongada),
            'El tipo de alerta debe ser extraccion_prolongada'
        );
        Assert::assertTrue(
            $alerta->estado()->equals(EstadoAlerta::Activa),
            'La alerta debe estar activa'
        );

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::ExtraccionProlongada),
            'La caja debe cambiar a estado "extraccion_prolongada"'
        );
    }

    // ==========================================
    // ESCENARIO: Devolución (detectada por el sistema como ingreso)
    // ==========================================

    #[Given('que existe una caja en estado "Extracción Prolongada"')]
    public function queExisteUnaCajaEnEstadoExtraccionProlongada(): void
    {
        $ranura = $this->sembrarGabineteConRanura(
            'GAB-EXT-PROL-001',
            'CAJA-EXT-PROL-001',
            ClasificacionTaxonomica::desde(subfamilia: 'Nymphalinae', genero: 'Nymphalis'),
        );

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        $caja->marcarExtraccionProlongada();
        $caja->pullEvents();
        $this->cajaRepo->guardar($caja);

        // Alerta activa que la devolución debe resolver automáticamente.
        $alerta = AlertaUbicacion::generar(
            id: $this->alertaRepo->nextIdentity(),
            cajaId: $caja->id(),
            tipo: TipoAlerta::ExtraccionProlongada,
            datosContexto: ['motivo' => 'tiempo_extraccion_excedido'],
        );
        $this->alertaRepo->guardar($alerta);

        $persistida = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estadoActual()->equals(EstadoCaja::ExtraccionProlongada),
            'La caja debe estar en estado "extraccion_prolongada" como precondición'
        );
        Assert::assertNotNull($this->ranuraOrigenId, 'Debe existir la ranura para la devolución');
        Assert::assertFalse($ranura->estaOcupada(), 'La ranura de devolución debe estar libre');
    }

    #[When('el sistema detecta que la caja fue insertada en su ranura del gabinete')]
    public function elSistemaDetectaQueLaCajaFueInsertadaEnSuRanura(): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja en estado previo');
        Assert::assertNotNull($this->ranuraOrigenId, 'Se requiere la ranura de destino para la devolución');

        try {
            $this->ultimaIngresoOutput = $this->ingresoHandler->handle(
                new RegistrarIngresoCajaInput(
                    cajaId: (string) $this->cajaId,
                    ranuraId: (string) $this->ranuraOrigenId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la devolución se registra')]
    public function laDevolucionSeRegistra(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción al registrar la devolución: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaIngresoOutput, 'El handler debe retornar una respuesta');

        $ubicacionActiva = $this->ubicacionRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNotNull($ubicacionActiva, 'Debe existir un registro activo de ubicación tras la devolución');
        Assert::assertNull($ubicacionActiva->retiradaEn(), 'La ubicación activa no debe tener fecha de retiro');
    }

    #[Then('el estado de la caja vuelve a "En Gabinete"')]
    public function elEstadoDeLaCajaVuelveAEnGabinete(): void
    {
        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja, 'La caja debe existir en el repositorio');
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnGabinete),
            'La caja debe cambiar a estado "en_gabinete" tras la devolución'
        );

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNull($alerta, 'La devolución debe resolver la alerta de extracción prolongada');
    }

    // ==========================================
    // Factory Methods
    // ==========================================

    private function sembrarGabineteConRanura(
        string $codigoGabinete,
        string $codigoCaja,
        ?ClasificacionTaxonomica $clasificacion = null,
    ): RanuraGabinete {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde($codigoGabinete),
            nombre: 'Gabinete Extracción',
            totalRanuras: 5,
        );
        $this->gabineteRepo->guardar($gabinete);

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: 1,
        );
        $this->ranuraRepo->guardar($ranura);
        $this->ranuraOrigenId = $ranura->id();

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde($codigoCaja),
            clasificacionTaxonomica: $clasificacion,
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaId = $caja->id();

        return $ranura;
    }
}
