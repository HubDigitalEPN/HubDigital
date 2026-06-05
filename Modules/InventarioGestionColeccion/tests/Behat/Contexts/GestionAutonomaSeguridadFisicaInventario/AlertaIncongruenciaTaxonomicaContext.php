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
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Horario;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
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
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryOrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryRanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayRepository;
use PHPUnit\Framework\Assert;

final class AlertaIncongruenciaTaxonomicaContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarIngresoCajaHandler $ingresoHandler;

    // ── Repositories ─────────────────────────────────────────────────────────

    private InMemoryCajaRepository $cajaRepo;

    private InMemoryRanuraGabineteRepository $ranuraRepo;

    private InMemoryGabineteRepository $gabineteRepo;

    private InMemoryUbicacionCajaRepository $ubicacionRepo;

    private InMemoryEventoCicloIotRepository $eventoRepo;

    private InMemoryAlertaUbicacionRepository $alertaRepo;

    private InMemoryNotificacionRepository $notificacionRepo;

    private InMemoryHorarioRepository $horarioRepo;

    private InMemoryUnitTrayRepository $unitTrayRepo;

    private InMemoryOrdenEsperadoFamiliasRepository $ordenFamiliasRepo;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?CajaId $cajaAInsertar = null;

    private ?RanuraId $ranuraDestino = null;

    private ?GabineteId $gabineteActual = null;

    private ?string $observacionEsperada = null;

    private ?RegistrarIngresoCajaOutput $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

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
        $this->unitTrayRepo = new InMemoryUnitTrayRepository;
        $this->ordenFamiliasRepo = new InMemoryOrdenEsperadoFamiliasRepository;

        $this->horarioRepo->guardar(Horario::crear(
            id: HorarioId::generar(),
            horaInicio: 8,
            horaFin: 18,
        ));

        $horarioFake = new FakeHorarioValidadorAdapter($this->horarioRepo);
        $horarioFake->setFueraDeHorario(false);

        self::$app->instance(HorarioValidadorPort::class, $horarioFake);
        self::$app->instance(HorarioRepository::class, $this->horarioRepo);
        self::$app->instance(EventPublisherPort::class, new FakeEventPublisherAdapter);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(ContextoEjecucionPort::class, new FakeContextoEjecucionAdapter);
        self::$app->instance(CajaRepository::class, $this->cajaRepo);
        self::$app->instance(RanuraGabineteRepository::class, $this->ranuraRepo);
        self::$app->instance(GabineteRepository::class, $this->gabineteRepo);
        self::$app->instance(UbicacionCajaRepository::class, $this->ubicacionRepo);
        self::$app->instance(EventoCicloIotRepository::class, $this->eventoRepo);
        self::$app->instance(AlertaUbicacionRepository::class, $this->alertaRepo);
        self::$app->instance(NotificacionRepository::class, $this->notificacionRepo);
        self::$app->instance(UnitTrayRepository::class, $this->unitTrayRepo);
        self::$app->instance(OrdenEsperadoFamiliasRepository::class, $this->ordenFamiliasRepo);

        $this->ingresoHandler = $this->make(RegistrarIngresoCajaHandler::class);
    }

    // ==========================================
    // ESQUEMA DEL ESCENARIO: El sistema evalúa el orden taxonómico al insertar
    // ==========================================

    #[Given('que el gabinete tiene cajas vecinas organizadas en orden taxonómico')]
    public function queElGabineteTieneCajasVecinasOrganizadasEnOrdenTaxonomico(): void
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde('GAB-TAX-001'),
            nombre: 'Gabinete Taxonomía Entomológica',
            totalRanuras: 10,
        );
        $this->gabineteRepo->guardar($gabinete);
        $this->gabineteActual = $gabinete->id();

        $gabineteGuardado = $this->gabineteRepo->buscarPorId($gabinete->id());
        Assert::assertNotNull($gabineteGuardado, 'El gabinete debe quedar persistido antes de continuar');
        Assert::assertTrue($gabineteGuardado->activo(), 'El gabinete debe estar activo');

        // Ranura 1 (anterior): Acraeinae / Acraea — primer taxón en orden alfabético
        $this->sembrarCajaVecinaEnGabinete(
            gabineteId: $gabinete->id(),
            numeroRanura: 1,
            clasificacion: ClasificacionTaxonomica::desde(subfamilia: 'Acraeinae', genero: 'Acraea'),
            codigoCaja: 'CAJA-VEC-ANT-001',
        );

        // Ranura 5 (siguiente): Nymphalinae / Nymphalis — tercer taxón en orden alfabético
        $this->sembrarCajaVecinaEnGabinete(
            gabineteId: $gabinete->id(),
            numeroRanura: 5,
            clasificacion: ClasificacionTaxonomica::desde(subfamilia: 'Nymphalinae', genero: 'Nymphalis'),
            codigoCaja: 'CAJA-VEC-SIG-001',
        );

        // Ranura 3 (destino): libre — el hueco donde se insertará la caja bajo prueba
        $ranuraDestino = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: 3,
        );
        $this->ranuraRepo->guardar($ranuraDestino);
        $this->ranuraDestino = $ranuraDestino->id();

        $ranuraGuardada = $this->ranuraRepo->buscarPorId($ranuraDestino->id());
        Assert::assertNotNull($ranuraGuardada, 'La ranura destino debe estar persistida');
        Assert::assertFalse($ranuraGuardada->estaOcupada(), 'La ranura destino debe estar libre antes del ingreso');
    }

    /**
     * Posicion values from the Esquema table:
     *   "en orden taxonómico correcto"            → Biblidinae/Byblia  (Acraeinae < Biblidinae < Nymphalinae) ✓
     *   "fuera del orden alfabético por subfamilia" → Zygaeninae/Zygaena (Zygaeninae > Nymphalinae) ✗
     *   "fuera del orden alfabético por género"   → Nymphalinae/Zygaena (same subfamilia, Zygaena > Nymphalis) ✗
     */
    #[Given('/^la caja a insertar tiene una clasificación (.+) respecto a las cajas adyacentes$/u')]
    public function laCajaAInsertarTieneUnaClasificacion(string $posicion): void
    {
        Assert::assertNotNull($this->gabineteActual, 'Se requiere gabinete sembrado (@Given anterior)');
        Assert::assertNotNull($this->ranuraDestino, 'Se requiere ranura destino sembrada (@Given anterior)');

        $clasificacion = match ($posicion) {
            'en orden taxonómico correcto' => ClasificacionTaxonomica::desde(subfamilia: 'Biblidinae', genero: 'Byblia'),
            'fuera del orden alfabético por subfamilia' => ClasificacionTaxonomica::desde(subfamilia: 'Zygaeninae', genero: 'Zygaena'),
            'fuera del orden alfabético por género' => ClasificacionTaxonomica::desde(subfamilia: 'Nymphalinae', genero: 'Zygaena'),
            default => throw new \InvalidArgumentException("Posición taxonómica no reconocida: '{$posicion}'"),
        };

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde('CAJA-TARGET-001'),
            clasificacionTaxonomica: $clasificacion,
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaAInsertar = $caja->id();

        $cajaGuardada = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaGuardada, 'La caja a insertar debe estar persistida');
        Assert::assertNotNull($cajaGuardada->clasificacionTaxonomica(), 'La caja debe tener clasificación taxonómica asignada');
        Assert::assertFalse($cajaGuardada->clasificacionTaxonomica()->estaVacia(), 'La clasificación no debe estar vacía');
        Assert::assertTrue(
            $cajaGuardada->estadoActual()->equals(EstadoCaja::EnTransito),
            'La caja debe estar en EnTransito antes del ingreso'
        );
    }

    // ==========================================
    // ESCENARIO: El sistema registra con alerta el ingreso de una caja sin unit trays
    // ==========================================

    #[Given('que existe una caja sin unit trays asignados')]
    public function queExisteUnaCajaSinUnitTraysAsignados(): void
    {
        $this->sembrarGabineteConRanuraLibre();

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde('CAJA-SIN-TRAYS-001'),
            clasificacionTaxonomica: null,
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaAInsertar = $caja->id();

        $cajaGuardada = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaGuardada, 'La caja debe estar persistida');
        Assert::assertNull($cajaGuardada->clasificacionTaxonomica(), 'La caja no debe tener clasificación taxonómica previa');
        Assert::assertTrue(
            $cajaGuardada->estadoActual()->equals(EstadoCaja::EnTransito),
            'La caja debe estar en EnTransito antes del ingreso'
        );

        $unitTrays = $this->unitTrayRepo->buscarPorCaja($caja->id());
        Assert::assertEmpty($unitTrays, 'La caja no debe tener UnitTrays asignados en este escenario');
    }

    // ==========================================
    // ESCENARIO: El sistema registra sin alerta el ingreso de una caja especial
    // ==========================================

    #[Given('/^que existe una caja marcada como "([^"]+)" con la observación "([^"]+)"$/u')]
    public function queExisteUnaCajaMarcadaComoConLaObservacion(string $tipoCaja, string $observacion): void
    {
        Assert::assertSame('Caja Especial', $tipoCaja, 'El tipo de caja reconocido para este escenario es "Caja Especial"');
        Assert::assertNotEmpty($observacion, 'Una caja especial requiere observación no vacía');

        $this->sembrarGabineteConRanuraLibre();
        $this->observacionEsperada = $observacion;

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde('CAJA-ESPECIAL-001'),
            esEspecial: true,
            observacion: $observacion,
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaAInsertar = $caja->id();

        $cajaGuardada = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaGuardada, 'La caja especial debe estar persistida');
        Assert::assertTrue($cajaGuardada->esEspecial(), 'La caja debe estar marcada como especial');
        Assert::assertSame($observacion, $cajaGuardada->observacion(), 'La observación debe coincidir con la sembrada');
        Assert::assertTrue(
            $cajaGuardada->estadoActual()->equals(EstadoCaja::EnTransito),
            'La caja debe estar en EnTransito antes del ingreso'
        );
    }

    // ==========================================
    // CUANDO — compartido entre los tres escenarios / esquema
    // ==========================================

    #[When('el sistema detecta que la caja fue insertada en el gabinete')]
    public function elSistemaDetectaQueLaCajaFueInsertadaEnElGabinete(): void
    {
        Assert::assertNotNull($this->cajaAInsertar, 'Se requiere una caja sembrada en el paso @Given');
        Assert::assertNotNull($this->ranuraDestino, 'Se requiere una ranura destino sembrada en el paso @Given');

        $cajaPrevia = $this->cajaRepo->buscarPorId($this->cajaAInsertar);
        Assert::assertNotNull($cajaPrevia, 'La caja debe existir en el repositorio antes de ejecutar el handler');
        Assert::assertTrue(
            $cajaPrevia->estadoActual()->equals(EstadoCaja::EnTransito),
            'La precondición del handler exige que la caja esté en EnTransito'
        );

        $ranuraPrevia = $this->ranuraRepo->buscarPorId($this->ranuraDestino);
        Assert::assertNotNull($ranuraPrevia, 'La ranura destino debe existir en el repositorio');
        Assert::assertFalse($ranuraPrevia->estaOcupada(), 'La ranura destino debe estar libre antes del ingreso');

        try {
            $this->ultimaRespuesta = $this->ingresoHandler->handle(
                new RegistrarIngresoCajaInput(
                    cajaId: (string) $this->cajaAInsertar,
                    ranuraId: (string) $this->ranuraDestino,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // ==========================================
    // ENTONCES — Esquema: orden correcto → sin alertas
    // ==========================================

    #[Then('el ingreso se registra exitosamente sin alertas')]
    public function elIngresoSeRegistraExitosamenteSinAlertas(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');
        Assert::assertFalse($this->ultimaRespuesta->alertaGenerada, 'No debe generarse ninguna alerta en ingreso correcto');
        Assert::assertSame(
            EstadoCaja::EnGabinete->valor(),
            $this->ultimaRespuesta->estadoCaja,
            'El output debe indicar que la caja quedó en EnGabinete'
        );

        $caja = $this->cajaRepo->buscarPorId($this->cajaAInsertar);
        Assert::assertNotNull($caja);
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnGabinete),
            'La caja debe quedar registrada en el gabinete'
        );

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaAInsertar);
        Assert::assertNull($alerta, 'No debe existir ninguna alerta activa cuando el orden taxonómico es correcto');

        $ranura = $this->ranuraRepo->buscarPorId($this->ranuraDestino);
        Assert::assertNotNull($ranura);
        Assert::assertTrue($ranura->estaOcupada(), 'La ranura destino debe quedar ocupada');
    }

    // ==========================================
    // ENTONCES — Esquema: fuera de orden → alerta suave (ingreso no bloqueado)
    // ==========================================

    #[Then('/^el ingreso se registra y se genera una alerta suave de "([^"]+)"$/u')]
    public function elIngresoSeRegistraYSeGeneraUnaAlertaSuaveDe(string $nombreAlerta): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'La alerta es suave y no debe bloquear el ingreso: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->alertaGenerada,
            'El output debe indicar que se generó una alerta'
        );

        $tipoEsperado = $this->resolverTipoAlerta($nombreAlerta);

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaAInsertar);
        Assert::assertNotNull($alerta, "Debe existir una alerta activa de tipo '{$nombreAlerta}'");
        Assert::assertTrue(
            $alerta->tipo()->equals($tipoEsperado),
            "Se esperaba alerta '{$tipoEsperado->valor()}' pero se encontró '{$alerta->tipo()->valor()}'"
        );
        Assert::assertTrue(
            $alerta->estado()->equals(EstadoAlerta::Activa),
            'La alerta debe estar en estado Activa'
        );

        // Alerta suave: el ingreso procede, la caja queda registrada en el gabinete
        $caja = $this->cajaRepo->buscarPorId($this->cajaAInsertar);
        Assert::assertNotNull($caja);
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnGabinete),
            'Alerta suave: la caja debe seguir registrada en el gabinete, el flujo no se bloquea'
        );

        $ranura = $this->ranuraRepo->buscarPorId($this->ranuraDestino);
        Assert::assertNotNull($ranura);
        Assert::assertTrue($ranura->estaOcupada(), 'La ranura destino debe quedar ocupada aunque se genere alerta');
    }

    // ==========================================
    // ENTONCES — Escenario: caja sin unit trays
    // ==========================================

    #[Then('el ingreso se registra en el gabinete')]
    public function elIngresoSeRegistraEnElGabinete(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->ubicacionCajaId,
            'Debe crearse un registro de ubicación para el ingreso'
        );
    }

    #[Then('/^se genera una alerta de "([^"]+)"$/u')]
    public function seGeneraUnaAlertaDe(string $nombreAlerta): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción previa al paso @Then');

        $tipoEsperado = $this->resolverTipoAlerta($nombreAlerta);

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaAInsertar);
        Assert::assertNotNull($alerta, "Debe existir una alerta activa de tipo '{$nombreAlerta}'");
        Assert::assertTrue(
            $alerta->tipo()->equals($tipoEsperado),
            "Se esperaba alerta '{$tipoEsperado->valor()}' pero se encontró '{$alerta->tipo()->valor()}'"
        );
        Assert::assertTrue(
            $alerta->estado()->equals(EstadoAlerta::Activa),
            'La alerta debe estar en estado Activa'
        );
    }

    #[Then('/^la caja queda en estado "([^"]+)"$/u')]
    public function laCajaQuedaEnEstado(string $nombreEstado): void
    {
        $estadoEsperado = match ($nombreEstado) {
            'Pendiente de Clasificación' => EstadoCaja::PendienteClasificacion,
            'En Gabinete' => EstadoCaja::EnGabinete,
            'En Tránsito' => EstadoCaja::EnTransito,
            default => throw new \InvalidArgumentException("Estado de caja no reconocido: '{$nombreEstado}'"),
        };

        $caja = $this->cajaRepo->buscarPorId($this->cajaAInsertar);
        Assert::assertNotNull($caja, 'La caja debe existir en el repositorio para verificar su estado');
        Assert::assertTrue(
            $caja->estadoActual()->equals($estadoEsperado),
            "Se esperaba estado '{$estadoEsperado->valor()}' pero la caja tiene '{$caja->estadoActual()->valor()}'"
        );
    }

    // ==========================================
    // ENTONCES — Escenario: caja especial
    // ==========================================

    #[Then('el ingreso se registra con su observación')]
    public function elIngresoSeRegistraConSuObservacion(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');
        Assert::assertNotNull($this->observacionEsperada, 'Se requiere observación sembrada en el paso @Given');

        $caja = $this->cajaRepo->buscarPorId($this->cajaAInsertar);
        Assert::assertNotNull($caja, 'La caja debe existir en el repositorio');
        Assert::assertTrue($caja->esEspecial(), 'La caja debe seguir marcada como especial tras el ingreso');
        Assert::assertSame(
            $this->observacionEsperada,
            $caja->observacion(),
            'La observación debe conservarse intacta después del ingreso'
        );
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnGabinete),
            'La caja especial debe quedar registrada en el gabinete'
        );
    }

    #[Then('no se genera ninguna alerta de orden taxonómico')]
    public function noSeGeneraNingunaAlertaDeOrdenTaxonomico(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción previa al paso @Then');

        $todasLasAlertas = $this->alertaRepo->buscarTodas();
        $alertasTaxonomicasDeLaCaja = array_values(array_filter(
            $todasLasAlertas,
            fn (AlertaUbicacion $a) => $a->cajaId()->equals($this->cajaAInsertar)
                && in_array($a->tipo(), [TipoAlerta::OrdenTaxonomicoFueraDeSecuencia, TipoAlerta::FamiliaNoAsignada], true)
        ));

        Assert::assertEmpty(
            $alertasTaxonomicasDeLaCaja,
            'Una caja especial está exenta de verificación taxonómica: no debe generarse ninguna alerta de ese tipo'
        );
    }

    // ==========================================
    // Factory Methods privados
    // ==========================================

    /**
     * Crea un gabinete con una ranura libre en la posición 1.
     * Usado por los escenarios que no necesitan vecinas (sin unit trays, caja especial).
     */
    private function sembrarGabineteConRanuraLibre(): RanuraGabinete
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde('GAB-TEST-001'),
            nombre: 'Gabinete Prueba Funcional',
            totalRanuras: 10,
        );
        $this->gabineteRepo->guardar($gabinete);
        $this->gabineteActual = $gabinete->id();

        $gabineteGuardado = $this->gabineteRepo->buscarPorId($gabinete->id());
        Assert::assertNotNull($gabineteGuardado, 'El gabinete debe quedar persistido');
        Assert::assertTrue($gabineteGuardado->activo(), 'El gabinete debe estar activo');

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: 1,
        );
        $this->ranuraRepo->guardar($ranura);
        $this->ranuraDestino = $ranura->id();

        $ranuraGuardada = $this->ranuraRepo->buscarPorId($ranura->id());
        Assert::assertNotNull($ranuraGuardada, 'La ranura destino debe estar persistida');
        Assert::assertFalse($ranuraGuardada->estaOcupada(), 'La ranura destino debe estar libre');

        return $ranuraGuardada;
    }

    /**
     * Siembra una caja vecina ya ubicada en una ranura del gabinete.
     * Usa ingresarEnRanura() directamente para simular el estado previo sin pasar por el handler.
     */
    private function sembrarCajaVecinaEnGabinete(
        GabineteId $gabineteId,
        int $numeroRanura,
        ClasificacionTaxonomica $clasificacion,
        string $codigoCaja,
    ): void {
        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabineteId,
            numeroRanura: $numeroRanura,
        );

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde($codigoCaja),
            clasificacionTaxonomica: $clasificacion,
        );

        $caja->ingresarEnRanura($ranura->id());
        $caja->pullEvents(); // descartar eventos de siembra para no contaminar el estado del escenario
        $ranura->asignarCaja($caja->id());

        $this->cajaRepo->guardar($caja);
        $this->ranuraRepo->guardar($ranura);

        $cajaGuardada = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaGuardada, "La caja vecina '{$codigoCaja}' debe quedar persistida");
        Assert::assertTrue(
            $cajaGuardada->estadoActual()->equals(EstadoCaja::EnGabinete),
            "La caja vecina '{$codigoCaja}' debe estar en estado EnGabinete"
        );
        Assert::assertNotNull($cajaGuardada->clasificacionTaxonomica());
        Assert::assertFalse(
            $cajaGuardada->clasificacionTaxonomica()->estaVacia(),
            "La caja vecina '{$codigoCaja}' debe tener clasificación taxonómica válida"
        );

        $ranuraGuardada = $this->ranuraRepo->buscarPorId($ranura->id());
        Assert::assertNotNull($ranuraGuardada, "La ranura {$numeroRanura} debe quedar persistida");
        Assert::assertTrue(
            $ranuraGuardada->estaOcupada(),
            "La ranura {$numeroRanura} debe estar ocupada por la caja vecina '{$codigoCaja}'"
        );
    }

    private function resolverTipoAlerta(string $nombreMostrado): TipoAlerta
    {
        return match ($nombreMostrado) {
            'Orden Taxonómico Fuera de Secuencia' => TipoAlerta::OrdenTaxonomicoFueraDeSecuencia,
            'Familia No Asignada' => TipoAlerta::FamiliaNoAsignada,
            default => throw new \InvalidArgumentException("Nombre de alerta no reconocido: '{$nombreMostrado}'"),
        };
    }
}
