<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario;

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
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
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
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryNotificacionRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryRanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUbicacionCajaRepository;
use PHPUnit\Framework\Assert;

final class RegistroUbicacionCajasContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarIngresoCajaHandler $ingresoHandler;

    private RegistrarRetiroCajaHandler $retiroHandler;

    // ── Repositorios In-Memory ────────────────────────────────────────────────

    private InMemoryCajaRepository $cajaRepo;

    private InMemoryRanuraGabineteRepository $ranuraRepo;

    private InMemoryGabineteRepository $gabineteRepo;

    private InMemoryUbicacionCajaRepository $ubicacionRepo;

    private InMemoryEventoCicloIotRepository $eventoRepo;

    private InMemoryAlertaUbicacionRepository $alertaRepo;

    private InMemoryNotificacionRepository $notificacionRepo;

    // ── Fakes ─────────────────────────────────────────────────────────────────

    private FakeHorarioValidadorAdapter $horarioFake;

    private FakeEventPublisherAdapter $fakePublisher;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?CajaId $cajaId = null;

    private ?RanuraId $ranuraId = null;

    private ?RegistrarIngresoCajaOutput $ultimoIngresoOutput = null;

    private ?RegistrarRetiroCajaOutput $ultimoRetiroOutput = null;

    private ?\Throwable $excepcionCapturada = null;

    private ?string $accionEjecutada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->horarioFake = new FakeHorarioValidadorAdapter;
        $this->fakePublisher = new FakeEventPublisherAdapter;

        $this->cajaRepo = new InMemoryCajaRepository;
        $this->ranuraRepo = new InMemoryRanuraGabineteRepository;
        $this->gabineteRepo = new InMemoryGabineteRepository;
        $this->ubicacionRepo = new InMemoryUbicacionCajaRepository;
        $this->eventoRepo = new InMemoryEventoCicloIotRepository;
        $this->alertaRepo = new InMemoryAlertaUbicacionRepository;
        $this->notificacionRepo = new InMemoryNotificacionRepository;

        self::$app->instance(HorarioValidadorPort::class, $this->horarioFake);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(ContextoEjecucionPort::class, new FakeContextoEjecucionAdapter);
        self::$app->instance(CajaRepository::class, $this->cajaRepo);
        self::$app->instance(RanuraGabineteRepository::class, $this->ranuraRepo);
        self::$app->instance(GabineteRepository::class, $this->gabineteRepo);
        self::$app->instance(UbicacionCajaRepository::class, $this->ubicacionRepo);
        self::$app->instance(EventoCicloIotRepository::class, $this->eventoRepo);
        self::$app->instance(AlertaUbicacionRepository::class, $this->alertaRepo);
        self::$app->instance(NotificacionRepository::class, $this->notificacionRepo);

        $this->ingresoHandler = $this->make(RegistrarIngresoCajaHandler::class);
        $this->retiroHandler = $this->make(RegistrarRetiroCajaHandler::class);
    }

    // ==========================================
    // ESQUEMA: Registro de movimiento de una caja en una ranura del gabinete
    // ==========================================

    #[Given('que se está monitoreando una ranura del gabinete')]
    public function queSeEstáMonitoreandoUnaRanuraDelGabinete(): void
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde('GAB-MON-001'),
            nombre: 'Gabinete Monitoreado',
            totalRanuras: 5,
        );
        $this->gabineteRepo->guardar($gabinete);

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: 1,
            familiaTaxonomicaEsperadaId: 'Nymphalidae',
        );
        $this->ranuraRepo->guardar($ranura);
        $this->ranuraId = $ranura->id();

        $gabineteActivo = $this->gabineteRepo->buscarPorId($gabinete->id());
        Assert::assertNotNull($gabineteActivo, 'El gabinete debe estar registrado y activo');
        Assert::assertNotNull($this->ranuraId, 'Debe existir al menos una ranura para el gabinete');
    }

    #[Given('/^existe una caja entomológica en condición de (.+)$/u')]
    public function existeUnaCajaEntomológicaEnCondiciónDe(string $condicion_previa): void
    {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde('CAJA-MOV-001'),
            familiaTaxonomicaId: 'Nymphalidae',
            capacidadMaxima: 10,
        );

        if (str_contains($condicion_previa, 'registrada en una ranura')) {
            Assert::assertNotNull($this->ranuraId, 'Se requiere una ranura sembrada en el paso anterior');

            $caja->ingresarEnRanura($this->ranuraId);
            $this->cajaRepo->guardar($caja);

            $ubicacion = UbicacionCaja::registrar(
                id: $this->ubicacionRepo->nextIdentity(),
                cajaId: $caja->id(),
                ranuraGabineteId: $this->ranuraId,
                ingresadaEn: new \DateTimeImmutable,
            );
            $this->ubicacionRepo->guardar($ubicacion);

            $cajaPersistida = $this->cajaRepo->buscarPorId($caja->id());
            Assert::assertNotNull($cajaPersistida);
            Assert::assertTrue(
                $cajaPersistida->estadoActual()->equals(EstadoCaja::EnGabinete),
                'La caja debe estar "en_gabinete" para el escenario de retiro'
            );
        } else {
            $this->cajaRepo->guardar($caja);

            $cajaPersistida = $this->cajaRepo->buscarPorId($caja->id());
            Assert::assertNotNull($cajaPersistida);
            Assert::assertTrue(
                $cajaPersistida->estadoActual()->equals(EstadoCaja::EnTransito),
                'La caja debe estar "en_transito" para el escenario de ingreso'
            );
        }

        $this->cajaId = $caja->id();
    }

    #[When(':accion la caja entomológica en la ranura')]
    public function laCajaEntomológicaEnLaRanura(string $accion): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja sembrada en @Given');
        $this->accionEjecutada = $accion;

        try {
            if ($accion === 'ingreso') {
                Assert::assertNotNull($this->ranuraId, 'Se requiere una ranura disponible para el ingreso');
                $this->ultimoIngresoOutput = $this->ingresoHandler->handle(
                    new RegistrarIngresoCajaInput(
                        cajaId: (string) $this->cajaId,
                        ranuraId: (string) $this->ranuraId,
                    )
                );
            } else {
                $this->ultimoRetiroOutput = $this->retiroHandler->handle(
                    new RegistrarRetiroCajaInput(
                        cajaId: (string) $this->cajaId,
                        ranuraId: (string) $this->ranuraId,
                    )
                );
            }
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se debe registrar el evento de :accion')]
    public function seDebeRegistrarElEventoDe(string $accion): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );

        $tipoEvento = $accion === 'ingreso' ? 'caja_ingresada' : 'caja_retirada';
        $evento = $this->eventoRepo->buscarUltimoPorAgregadoYTipo(
            agregadoId: (string) $this->cajaId,
            tipoEvento: $tipoEvento,
        );

        Assert::assertNotNull($evento, "Debe existir un evento de tipo '{$tipoEvento}' para la caja");
        Assert::assertSame($tipoEvento, $evento->tipoEvento(), 'El tipo de evento debe coincidir con la acción');
        Assert::assertSame('caja', $evento->tipoAgregado(), 'El agregado del evento debe ser "caja"');
    }

    #[Then('/^el estado de la caja debe cambiar a (.+)$/u')]
    public function elEstadoDeLaCajaDebeCambiarA(string $estado_resultante): void
    {
        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja, 'La caja debe existir en el repositorio');

        $estadoEsperado = match (trim($estado_resultante)) {
            'En Gabinete' => EstadoCaja::EnGabinete,
            'En Tránsito' => EstadoCaja::EnTransito,
            default => throw new \InvalidArgumentException("Estado desconocido: {$estado_resultante}"),
        };

        Assert::assertTrue(
            $caja->estadoActual()->equals($estadoEsperado),
            "Se esperaba estado '{$estado_resultante}' pero la caja tiene '{$caja->estadoActual()->valor()}'"
        );
    }

    // ==========================================
    // ESCENARIO: Movimiento no autorizado fuera del horario establecido
    // ==========================================

    #[Given('que el horario autorizado de movimiento de ranuras está configurado')]
    public function queElHorarioAutorizadoDeMovimientoDeRanurasEstáConfigurado(): void
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde('GAB-HOR-001'),
            nombre: 'Gabinete con Horario',
            totalRanuras: 5,
        );
        $this->gabineteRepo->guardar($gabinete);

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: 1,
            familiaTaxonomicaEsperadaId: 'Nymphalidae',
        );
        $this->ranuraRepo->guardar($ranura);
        $this->ranuraId = $ranura->id();

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde('CAJA-HOR-001'),
            familiaTaxonomicaId: 'Nymphalidae',
            capacidadMaxima: 10,
        );
        $caja->ingresarEnRanura($this->ranuraId);
        $this->cajaRepo->guardar($caja);
        $this->cajaId = $caja->id();

        $ubicacion = UbicacionCaja::registrar(
            id: $this->ubicacionRepo->nextIdentity(),
            cajaId: $caja->id(),
            ranuraGabineteId: $this->ranuraId,
            ingresadaEn: (new \DateTimeImmutable)->modify('-2 hours'),
        );
        $this->ubicacionRepo->guardar($ubicacion);

        $cajaPersistida = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaPersistida, 'La caja debe estar registrada en gabinete');
        Assert::assertTrue(
            $cajaPersistida->estadoActual()->equals(EstadoCaja::EnGabinete),
            'La caja debe estar en gabinete para poder ser retirada'
        );
    }

    #[Given('la hora actual está fuera del horario autorizado')]
    public function laHoraActualEstáFueraDelHorarioAutorizado(): void
    {
        $this->horarioFake->setFueraDeHorario(true);
    }

    #[When('retiro una caja entomológica de su ranura')]
    public function retiroUnaCajaEntomológicaDeSuRanura(): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja sembrada en @Given');
        $this->accionEjecutada = 'retiro';

        try {
            $this->ultimoRetiroOutput = $this->retiroHandler->handle(
                new RegistrarRetiroCajaInput(
                    cajaId: (string) $this->cajaId,
                    ranuraId: (string) $this->ranuraId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se debe generar una alerta de "Movimiento No Autorizado"')]
    public function seDebeGenerarUnaAlertaDeMovimientoNoAutorizado(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba una excepción de dominio');
        Assert::assertNotNull($this->ultimoRetiroOutput, 'El handler de retiro debe retornar una respuesta');

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNotNull($alerta, 'Debe existir una alerta activa de movimiento no autorizado');
        Assert::assertTrue(
            $alerta->tipo()->equals(TipoAlerta::MovimientoNoAutorizado),
            'El tipo de alerta debe ser movimiento_no_autorizado'
        );
        Assert::assertTrue(
            $alerta->estado()->equals(EstadoAlerta::Activa),
            'La alerta debe estar en estado activo'
        );
    }

    #[Then('el curador responsable debe recibir una notificación inmediata')]
    public function elCuradorResponsableDebeRecibirUnaNotificaciónInmediata(): void
    {
        Assert::assertNotNull($this->cajaId);

        $notificacion = $this->notificacionRepo->buscarUltimaNotificacionPorCaja($this->cajaId);
        Assert::assertNotNull(
            $notificacion,
            'Debe haberse enviado una notificación al curador responsable'
        );
        Assert::assertSame(
            'movimiento_no_autorizado',
            $notificacion->tipo(),
            'La notificación debe ser de tipo movimiento_no_autorizado'
        );
    }
}
