<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen\ConsultarTrazabilidadEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen\ConsultarTrazabilidadEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryCajaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryGabineteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryRanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayRepository;
use PHPUnit\Framework\Assert;

/**
 * Cubre la feature trazabilidad_movimientos contra el modelo real
 * especimen → unitTray → caja: el curador consulta la línea de tiempo de un espécimen,
 * que reúne sus propios movimientos más los de los contenedores que lo albergan, con el
 * responsable de cada uno (incluido el sistema cuando no hay actor humano).
 */
final class TrazabilidadMovimientosContext extends BaseContext
{
    private InMemoryCajaRepository $cajaRepo;

    private InMemoryUnitTrayRepository $unitTrayRepo;

    private InMemoryUnitTrayEspecimenRepository $asignacionRepo;

    private InMemoryEventoCicloIotRepository $eventoRepo;

    private InMemoryGabineteRepository $gabineteRepo;

    private InMemoryRanuraGabineteRepository $ranuraRepo;

    private ConsultarTrazabilidadEspecimenHandler $handler;

    private ?string $especimenId = null;

    private ?string $cajaId = null;

    private string $contenedorTrayId = '';

    private string $contenedorCajaId = '';

    private int $secuenciaEspecimen = 0;

    private ?string $responsableEsperado = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private \DateTimeImmutable $reloj;

    public function __construct()
    {
        $this->cajaRepo = new InMemoryCajaRepository;
        $this->unitTrayRepo = new InMemoryUnitTrayRepository;
        $this->asignacionRepo = new InMemoryUnitTrayEspecimenRepository;
        $this->eventoRepo = new InMemoryEventoCicloIotRepository;
        $this->gabineteRepo = new InMemoryGabineteRepository;
        $this->ranuraRepo = new InMemoryRanuraGabineteRepository;
        $this->reloj = new \DateTimeImmutable('2026-06-01 09:00:00');

        self::$app->instance(EventoCicloIotRepository::class, $this->eventoRepo);
        self::$app->instance(UnitTrayEspecimenRepository::class, $this->asignacionRepo);
        self::$app->instance(UnitTrayRepository::class, $this->unitTrayRepo);
        self::$app->instance(CajaRepository::class, $this->cajaRepo);
        self::$app->instance(GabineteRepository::class, $this->gabineteRepo);
        self::$app->instance(RanuraGabineteRepository::class, $this->ranuraRepo);

        $this->handler = $this->make(ConsultarTrazabilidadEspecimenHandler::class);
    }

    // ==========================================
    // Consulta de la trazabilidad de un espécimen
    // ==========================================

    #[Given('que existe un espécimen con movimientos previos')]
    public function queExisteUnEspecimenConMovimientosPrevios(): void
    {
        $this->especimenId = (string) $this->especimenRepoNextId();
        $this->sembrarEvento('especimen', $this->especimenId, 'especimen_reubicado', [
            'origen_unit_tray' => 'tray-1',
            'destino_unit_tray' => 'tray-2',
        ], 'curador-001', ActorRol::Curador);
        $this->sembrarEvento('especimen', $this->especimenId, 'especimen_reubicado', [
            'origen_unit_tray' => 'tray-2',
            'destino_unit_tray' => 'tray-3',
        ], 'curador-001', ActorRol::Curador);
    }

    #[Given('que existe un espécimen sin movimientos')]
    public function queExisteUnEspecimenSinMovimientos(): void
    {
        $this->especimenId = (string) $this->especimenRepoNextId();
    }

    #[When('el curador consulta la trazabilidad del espécimen')]
    #[When('el curador consulta la trazabilidad del elemento reubicado')]
    public function elCuradorConsultaLaTrazabilidad(): void
    {
        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new ConsultarTrazabilidadEspecimenInput(especimenId: $this->especimenId)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el historial es retornado con los movimientos en orden cronológico')]
    public function elHistorialEsRetornadoEnOrdenCronologico(): void
    {
        $movimientos = $this->ultimaRespuesta->movimientos;
        Assert::assertNotEmpty($movimientos, 'El historial debe contener los movimientos previos');

        $previo = null;
        foreach ($movimientos as $movimiento) {
            if ($previo !== null) {
                Assert::assertGreaterThanOrEqual(
                    $previo,
                    $movimiento->ocurridoEn,
                    'Los movimientos deben venir en orden cronológico ascendente'
                );
            }
            $previo = $movimiento->ocurridoEn;
        }
    }

    #[Then('el historial es retornado vacío')]
    public function elHistorialEsRetornadoVacio(): void
    {
        Assert::assertSame([], $this->ultimaRespuesta->movimientos, 'El historial debe estar vacío');
    }

    // ==========================================
    // Origen, destino y responsable de cada movimiento
    // ==========================================

    #[Given('que un curador reubicó un espécimen entre unit trays')]
    public function queUnCuradorReubicoUnEspecimenEntreUnitTrays(): void
    {
        $this->responsableEsperado = ActorRol::Curador->valor();
        $this->especimenId = (string) $this->especimenRepoNextId();
        $this->sembrarEvento('especimen', $this->especimenId, 'especimen_reubicado', [
            'origen_unit_tray' => 'tray-origen',
            'destino_unit_tray' => 'tray-destino',
        ], 'curador-001', ActorRol::Curador);
    }

    #[Given('que un visitante habilitado reubicó un espécimen entre unit trays')]
    public function queUnVisitanteReubicoUnEspecimenEntreUnitTrays(): void
    {
        $this->responsableEsperado = ActorRol::Visitante->valor();
        $this->especimenId = (string) $this->especimenRepoNextId();
        $this->sembrarEvento('especimen', $this->especimenId, 'especimen_reubicado', [
            'origen_unit_tray' => 'tray-origen',
            'destino_unit_tray' => 'tray-destino',
        ], 'visitante-001', ActorRol::Visitante);
    }

    #[Given('que un curador reubicó un unit tray entre cajas')]
    public function queUnCuradorReubicoUnUnitTrayEntreCajas(): void
    {
        $this->responsableEsperado = ActorRol::Curador->valor();
        [$caja, $tray] = $this->sembrarCajaConTray('CAJA-TRAZA-001');

        $this->especimenId = (string) $this->especimenRepoNextId();
        $this->asignacionRepo->sincronizar($tray->id(), [$this->especimenId]);

        $this->sembrarEvento('unit_tray', (string) $tray->id(), 'unit_tray_reubicado', [
            'origen_caja' => 'caja-origen',
            'destino_caja' => (string) $caja->id(),
        ], 'curador-001', ActorRol::Curador);
    }

    #[Then('el registro del movimiento indica el origen, el destino y el momento en que ocurrió')]
    public function elRegistroIndicaOrigenDestinoYMomento(): void
    {
        $movimientos = $this->ultimaRespuesta->movimientos;
        Assert::assertNotEmpty($movimientos, 'Debe existir al menos un movimiento registrado');

        $movimiento = $movimientos[0];
        Assert::assertNotNull($movimiento->origen, 'El movimiento debe indicar el origen');
        Assert::assertNotNull($movimiento->destino, 'El movimiento debe indicar el destino');
        Assert::assertInstanceOf(\DateTimeImmutable::class, $movimiento->ocurridoEn);
    }

    #[Then('el registro identifica al curador como responsable')]
    #[Then('el registro identifica al visitante habilitado como responsable')]
    public function elRegistroIdentificaAlResponsable(): void
    {
        $movimiento = $this->ultimaRespuesta->movimientos[0];
        Assert::assertSame(
            $this->responsableEsperado,
            $movimiento->responsable,
            'El movimiento debe identificar al actor que lo realizó'
        );
    }

    // ==========================================
    // Movimiento de caja sin actor humano
    // ==========================================

    #[Given('que existe una caja ubicada en una ranura de un gabinete')]
    public function queExisteUnaCajaUbicadaEnUnaRanura(): void
    {
        [$caja] = $this->sembrarCajaConTray('CAJA-RANURA-001');
        $this->cajaId = (string) $caja->id();
    }

    #[When('la caja se mueve a otra ranura')]
    public function laCajaSeMueveAOtraRanura(): void
    {
        $ranuraDestino = $this->sembrarRanuraEnGabinete('GAB-001', 2);

        // Movimiento detectado por el ESP32/sistema: sin actor humano (actorId null).
        $this->sembrarEvento('caja', $this->cajaId, 'caja_reubicada', [
            'ranura_id' => (string) $ranuraDestino->id(),
        ], null, ActorRol::Sistema);
    }

    #[Then('el movimiento de la caja queda registrado en la trazabilidad sin un actor humano')]
    public function elMovimientoDeLaCajaQuedaRegistradoSinActorHumano(): void
    {
        $eventos = $this->eventoRepo->buscarPorAgregado('caja', $this->cajaId);
        Assert::assertNotEmpty($eventos, 'Debe registrarse el movimiento de la caja');

        $evento = $eventos[0];
        Assert::assertSame('caja_reubicada', $evento->tipoEvento());
        Assert::assertNull($evento->actorId(), 'Un movimiento sin actor humano no tiene actorId');
        Assert::assertSame(ActorRol::Sistema, $evento->actorRol(), 'El responsable debe ser el sistema');
    }

    // ==========================================
    // La trazabilidad incluye los contenedores
    // ==========================================

    #[Given('que existe un espécimen alojado en un unit tray dentro de una caja')]
    public function queExisteUnEspecimenAlojadoEnUnUnitTrayDentroDeUnaCaja(): void
    {
        [, $tray] = $this->sembrarCajaConTray('CAJA-CONTENEDOR-001');
        $this->especimenId = (string) $this->especimenRepoNextId();
        $this->asignacionRepo->sincronizar($tray->id(), [$this->especimenId]);
        $this->contenedorTrayId = (string) $tray->id();
        $this->contenedorCajaId = (string) $tray->cajaId();
    }

    #[Given('el unit tray y la caja que lo contienen registraron movimientos')]
    public function elUnitTrayYLaCajaRegistraronMovimientos(): void
    {
        $this->sembrarEvento('especimen', $this->especimenId, 'especimen_reubicado', [
            'origen_unit_tray' => 'tray-previo',
            'destino_unit_tray' => $this->contenedorTrayId,
        ], 'curador-001', ActorRol::Curador);
        $this->sembrarEvento('unit_tray', $this->contenedorTrayId, 'unit_tray_reubicado', [
            'origen_caja' => 'caja-previa',
            'destino_caja' => $this->contenedorCajaId,
        ], 'curador-001', ActorRol::Curador);
        $ranura = $this->sembrarRanuraEnGabinete('GAB-CONTENEDOR-001', 3);
        $this->sembrarEvento('caja', $this->contenedorCajaId, 'caja_reubicada', [
            'ranura_id' => (string) $ranura->id(),
        ], null, ActorRol::Sistema);
    }

    #[Then('el historial incluye los movimientos del unit tray y de la caja que lo contienen')]
    public function elHistorialIncluyeLosMovimientosDeLosContenedores(): void
    {
        $tipos = array_map(fn ($m): string => $m->tipo, $this->ultimaRespuesta->movimientos);

        Assert::assertContains('especimen_reubicado', $tipos, 'Debe incluir el movimiento del espécimen');
        Assert::assertContains('unit_tray_reubicado', $tipos, 'Debe incluir el movimiento del unit tray contenedor');
        Assert::assertContains('caja_reubicada', $tipos, 'Debe incluir el movimiento de la caja contenedora');
    }

    // ==========================================
    // Helpers
    // ==========================================

    /** @param array<string, mixed> $datos */
    private function sembrarEvento(
        string $tipoAgregado,
        string $agregadoId,
        string $tipoEvento,
        array $datos,
        ?string $actorId,
        ActorRol $actorRol,
    ): void {
        $this->reloj = $this->reloj->modify('+1 minute');
        $this->eventoRepo->guardar(EventoCicloIot::registrar(
            tipoAgregado: $tipoAgregado,
            agregadoId: $agregadoId,
            tipoEvento: $tipoEvento,
            versionEvento: 1,
            datos: $datos,
            actorId: $actorId,
            actorRol: $actorRol,
            ocurridoEn: $this->reloj,
        ));
    }

    /** @return array{0: Caja, 1: UnitTray} */
    private function sembrarCajaConTray(string $codigoCaja): array
    {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde($codigoCaja),
        );
        $this->cajaRepo->guardar($caja);

        $unitTray = UnitTray::crear(
            id: $this->unitTrayRepo->nextIdentity(),
            cajaId: $caja->id(),
            numero: $this->unitTrayRepo->siguienteNumero($caja->id()),
        );
        $this->unitTrayRepo->guardar($unitTray);

        return [$caja, $unitTray];
    }

    private function especimenRepoNextId(): string
    {
        return 'especimen-'.(++$this->secuenciaEspecimen);
    }

    private function sembrarRanuraEnGabinete(string $codigoGabinete, int $numeroRanura): RanuraGabinete
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde($codigoGabinete),
            nombre: $codigoGabinete,
            totalRanuras: $numeroRanura,
        );
        $this->gabineteRepo->guardar($gabinete);

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: $numeroRanura,
        );
        $this->ranuraRepo->guardar($ranura);

        return $ranura;
    }
}
