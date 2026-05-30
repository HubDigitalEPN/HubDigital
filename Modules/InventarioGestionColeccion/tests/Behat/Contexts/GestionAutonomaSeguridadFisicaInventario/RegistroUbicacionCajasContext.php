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
use PHPUnit\Framework\Assert;

final class RegistroUbicacionCajasContext extends BaseContext
{
    private RegistrarIngresoCajaHandler $ingresoHandler;

    private RegistrarRetiroCajaHandler $retiroHandler;

    private InMemoryCajaRepository $cajaRepo;

    private InMemoryRanuraGabineteRepository $ranuraRepo;

    private InMemoryGabineteRepository $gabineteRepo;

    private InMemoryUbicacionCajaRepository $ubicacionRepo;

    private InMemoryEventoCicloIotRepository $eventoRepo;

    private InMemoryAlertaUbicacionRepository $alertaRepo;

    private InMemoryNotificacionRepository $notificacionRepo;

    private InMemoryHorarioRepository $horarioRepo;

    private FakeHorarioValidadorAdapter $horarioFake;

    private FakeEventPublisherAdapter $fakePublisher;

    private ?CajaId $cajaId = null;

    private ?RanuraId $ranuraId = null;

    private ?RegistrarIngresoCajaOutput $ultimoIngresoOutput = null;

    private ?RegistrarRetiroCajaOutput $ultimoRetiroOutput = null;

    private ?\Throwable $excepcionCapturada = null;

    public function __construct()
    {
        $this->horarioRepo = new InMemoryHorarioRepository;
        $this->cajaRepo = new InMemoryCajaRepository;
        $this->ranuraRepo = new InMemoryRanuraGabineteRepository;
        $this->gabineteRepo = new InMemoryGabineteRepository;
        $this->ubicacionRepo = new InMemoryUbicacionCajaRepository;
        $this->eventoRepo = new InMemoryEventoCicloIotRepository;
        $this->alertaRepo = new InMemoryAlertaUbicacionRepository;
        $this->notificacionRepo = new InMemoryNotificacionRepository;

        $horarioDefault = Horario::crear(
            id: HorarioId::generar(),
            horaInicio: 8,
            horaFin: 18,
        );
        $this->horarioRepo->guardar($horarioDefault);

        $this->horarioFake = new FakeHorarioValidadorAdapter($this->horarioRepo);
        $this->fakePublisher = new FakeEventPublisherAdapter;

        self::$app->instance(HorarioValidadorPort::class, $this->horarioFake);
        self::$app->instance(HorarioRepository::class, $this->horarioRepo);
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
    // ESCENARIO 1: El curador ubica una caja recién ingresada
    // ==========================================

    #[Given('/^que la caja "([^"]+)" está disponible fuera de un gabinete$/u')]
    public function queLaCajaEstaDisponibleFueraDeUnGabinete(string $codigo): void
    {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde($codigo),
            clasificacionTaxonomica: ClasificacionTaxonomica::desde(subfamilia: 'Nymphalinae', genero: 'Nymphalis'),
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaId = $caja->id();
    }

    #[Given('/^que la ranura (\d+) del gabinete "([^"]+)" está libre$/u')]
    public function queLaRanuraDelGabineteEstaLibre(int $numero, string $codigoGabinete): void
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde($codigoGabinete),
            nombre: "Gabinete {$codigoGabinete}",
            totalRanuras: 5,
        );
        $this->gabineteRepo->guardar($gabinete);

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: $numero,
            familiaTaxonomicaEsperadaId: 'Nymphalidae',
        );
        $this->ranuraRepo->guardar($ranura);
        $this->ranuraId = $ranura->id();
    }

    #[When('/^la caja "([^"]+)" es colocada en la ranura (\d+) del gabinete "([^"]+)"$/u')]
    public function laCajaEsColocadaEnLaRanura(string $codigo, int $numero, string $codigoGabinete): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja sembrada en Dado');
        Assert::assertNotNull($this->ranuraId, 'Se requiere una ranura sembrada en Dado');

        try {
            $this->ultimoIngresoOutput = $this->ingresoHandler->handle(
                new RegistrarIngresoCajaInput(
                    cajaId: (string) $this->cajaId,
                    ranuraId: (string) $this->ranuraId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('/^el curador puede ver que "([^"]+)" se encuentra en la ranura (\d+) del gabinete "([^"]+)"$/u')]
    public function elCuradorPuedeVerQueLaCajaSeEncuentraEnLaRanura(string $codigo, int $numero, string $codigoGabinete): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnGabinete),
            'El curador debe poder ver la caja ubicada en una ranura del gabinete'
        );
    }

    // ==========================================
    // ESCENARIO 2 y 3: Dado compartido — caja ya ubicada en ranura
    // ==========================================

    #[Given('/^que la caja "([^"]+)" se encuentra en la ranura (\d+) del gabinete "([^"]+)"$/u')]
    public function queLaCajaSeEncuentraEnLaRanuraDelGabinete(string $codigo, int $numero, string $codigoGabinete): void
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde($codigoGabinete),
            nombre: "Gabinete {$codigoGabinete}",
            totalRanuras: 5,
        );
        $this->gabineteRepo->guardar($gabinete);

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: $numero,
            familiaTaxonomicaEsperadaId: 'Nymphalidae',
        );
        $this->ranuraRepo->guardar($ranura);
        $this->ranuraId = $ranura->id();

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde($codigo),
            clasificacionTaxonomica: ClasificacionTaxonomica::desde(subfamilia: 'Nymphalinae', genero: 'Nymphalis'),
        );
        $caja->ingresarEnRanura($this->ranuraId);
        $this->cajaRepo->guardar($caja);
        $this->cajaId = $caja->id();

        $ubicacion = UbicacionCaja::registrar(
            id: $this->ubicacionRepo->nextIdentity(),
            cajaId: $caja->id(),
            ranuraGabineteId: $this->ranuraId,
            ingresadaEn: new \DateTimeImmutable,
        );
        $this->ubicacionRepo->guardar($ubicacion);
    }

    #[Given('que el movimiento ocurre fuera del horario autorizado de la colección')]
    public function queElMovimientoOcurreFueraDelHorarioAutorizado(): void
    {
        $this->horarioFake->setFueraDeHorario(true);
    }

    #[When('/^la caja "([^"]+)" es retirada de su ranura$/u')]
    public function laCajaEsRetiradaDeSuRanura(string $codigo): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja sembrada en Dado');
        Assert::assertNotNull($this->ranuraId, 'Se requiere una ranura sembrada en Dado');

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

    #[Then('/^el curador puede ver que "([^"]+)" no está ubicada en ninguna ranura$/u')]
    public function elCuradorPuedeVerQueLaCajaNoEstaUbicada(string $codigo): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnTransito),
            'El curador debe poder ver que la caja ya no está en ninguna ranura'
        );
    }

    #[Then('el curador recibe una alerta de movimiento no autorizado')]
    public function elCuradorRecibeUnaAlertaDeMovimientoNoAutorizado(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción');
        Assert::assertNotNull($this->ultimoRetiroOutput, 'El retiro debe haber completado');

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNotNull($alerta, 'Debe existir una alerta activa de movimiento no autorizado');
        Assert::assertTrue(
            $alerta->tipo()->equals(TipoAlerta::MovimientoNoAutorizado),
            'El tipo de alerta debe ser movimiento_no_autorizado'
        );
        Assert::assertTrue(
            $alerta->estado()->equals(EstadoAlerta::Activa),
            'La alerta debe estar activa'
        );
    }
}
