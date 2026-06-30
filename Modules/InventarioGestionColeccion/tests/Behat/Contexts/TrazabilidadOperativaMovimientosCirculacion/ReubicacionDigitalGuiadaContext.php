<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarEspecimenes\ReubicarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarEspecimenes\ReubicarEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarUnitTray\ReubicarUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarUnitTray\ReubicarUnitTrayInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Visitante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\ReubicacionNoAutorizadaException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes\FakeClasificacionTaxonomicaAdapter;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes\FakeContextoEjecucionAdapter;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes\PassThroughTransactionManagerAdapter;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryCajaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryVisitanteRepository;
use PHPUnit\Framework\Assert;

/**
 * Cubre la feature reubicacion_digital_guiada contra el modelo real
 * especimen → unitTray → caja: reubicación de especímenes a otro unit tray,
 * de unit trays a otra caja, advertencia taxonómica suave (confirmar/cancelar)
 * y la autorización del visitante (habilitado / no habilitado).
 */
final class ReubicacionDigitalGuiadaContext extends BaseContext
{
    private InMemoryCajaRepository $cajaRepo;

    private InMemoryUnitTrayRepository $unitTrayRepo;

    private InMemoryUnitTrayEspecimenRepository $asignacionRepo;

    private InMemoryEspecimenRepository $especimenRepo;

    private InMemoryTaxonRepository $taxonRepo;

    private InMemoryEventoCicloIotRepository $eventoRepo;

    private InMemoryVisitanteRepository $visitanteRepo;

    private FakeClasificacionTaxonomicaAdapter $clasificacionPort;

    private FakeContextoEjecucionAdapter $contexto;

    private ReubicarEspecimenesHandler $reubicarEspecimenesHandler;

    private ReubicarUnitTrayHandler $reubicarUnitTrayHandler;

    /** @var string[] */
    private array $especimenIds = [];

    private ?string $origenTrayId = null;

    private ?string $destinoTrayId = null;

    private ?string $trayReubicadoId = null;

    private ?string $destinoCajaId = null;

    private ?string $visitanteId = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    public function __construct()
    {
        $this->cajaRepo = new InMemoryCajaRepository;
        $this->unitTrayRepo = new InMemoryUnitTrayRepository;
        $this->asignacionRepo = new InMemoryUnitTrayEspecimenRepository;
        $this->especimenRepo = new InMemoryEspecimenRepository;
        $this->taxonRepo = new InMemoryTaxonRepository;
        $this->eventoRepo = new InMemoryEventoCicloIotRepository;
        $this->visitanteRepo = new InMemoryVisitanteRepository;
        $this->clasificacionPort = new FakeClasificacionTaxonomicaAdapter;
        $this->contexto = new FakeContextoEjecucionAdapter;

        self::$app->instance(CajaRepository::class, $this->cajaRepo);
        self::$app->instance(UnitTrayRepository::class, $this->unitTrayRepo);
        self::$app->instance(UnitTrayEspecimenRepository::class, $this->asignacionRepo);
        self::$app->instance(EspecimenRepositoryInterface::class, $this->especimenRepo);
        self::$app->instance(EventoCicloIotRepository::class, $this->eventoRepo);
        self::$app->instance(VisitanteRepositoryInterface::class, $this->visitanteRepo);
        self::$app->instance(ClasificacionTaxonomicaPort::class, $this->clasificacionPort);
        self::$app->instance(ContextoEjecucionPort::class, $this->contexto);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);

        $this->reubicarEspecimenesHandler = $this->make(ReubicarEspecimenesHandler::class);
        $this->reubicarUnitTrayHandler = $this->make(ReubicarUnitTrayHandler::class);
    }

    // ==========================================
    // Reubicación de especímenes a otro unit tray
    // ==========================================

    #[Given('que existe un espécimen asignado a un unit tray de origen')]
    #[Given('existe un espécimen asignado a un unit tray de origen')]
    public function queExisteUnEspecimenAsignadoAUnUnitTrayDeOrigen(): void
    {
        [, $tray] = $this->sembrarCajaConTray('CAJA-ORIGEN-001');
        $this->origenTrayId = (string) $tray->id();

        $especimenId = $this->sembrarEspecimen('MEPN-REUB-001', $this->clasificacionNymphalidae());
        $this->asignacionRepo->sincronizar($tray->id(), [$especimenId]);
        $this->especimenIds = [$especimenId];
    }

    #[Given('existe un unit tray de destino donde el espécimen respeta el orden taxonómico')]
    public function existeUnUnitTrayDeDestinoDondeElEspecimenRespetaElOrden(): void
    {
        // Tray vacío: el espécimen movido fija la clasificación dominante, así que encaja.
        [, $tray] = $this->sembrarCajaConTray('CAJA-DESTINO-001');
        $this->destinoTrayId = (string) $tray->id();
    }

    #[Given('que existen varios especímenes asignados a unit trays de origen')]
    public function queExistenVariosEspecimenesAsignadosAUnitTraysDeOrigen(): void
    {
        $ids = [];
        foreach (['MEPN-REUB-101', 'MEPN-REUB-102'] as $i => $codigo) {
            [, $tray] = $this->sembrarCajaConTray('CAJA-ORIGEN-1'.$i);
            $especimenId = $this->sembrarEspecimen($codigo, $this->clasificacionNymphalidae());
            $this->asignacionRepo->sincronizar($tray->id(), [$especimenId]);
            $ids[] = $especimenId;
        }
        $this->especimenIds = $ids;
    }

    #[Given('existe un unit tray de destino con espacio disponible')]
    public function existeUnUnitTrayDeDestinoConEspacioDisponible(): void
    {
        [, $tray] = $this->sembrarCajaConTray('CAJA-DESTINO-002');
        $this->destinoTrayId = (string) $tray->id();
    }

    #[Given('en el unit tray de destino el espécimen quedaría fuera del orden taxonómico')]
    public function enElUnitTrayDeDestinoElEspecimenQuedariaFueraDelOrden(): void
    {
        // El tray destino ya alberga una familia distinta dominante: el espécimen
        // Nymphalidae movido queda fuera del orden (alerta suave).
        [, $tray] = $this->sembrarCajaConTray('CAJA-DESTINO-FAM-001');
        $this->destinoTrayId = (string) $tray->id();

        $residentes = [
            $this->sembrarEspecimen('MEPN-RES-001', $this->clasificacionPapilionidae()),
            $this->sembrarEspecimen('MEPN-RES-002', $this->clasificacionPapilionidae()),
        ];
        $this->asignacionRepo->sincronizar($tray->id(), $residentes);
    }

    #[Given('existe un unit tray de destino en una caja especial donde el espécimen quedaría fuera del orden taxonómico')]
    public function existeUnUnitTrayDeDestinoEnUnaCajaEspecialFueraDelOrden(): void
    {
        // Caja especial: exenta de la verificación taxonómica, pero la trazabilidad
        // del movimiento debe registrarse igual.
        [, $tray] = $this->sembrarCajaConTray('CAJA-DESTINO-ESP-001', esEspecial: true);
        $this->destinoTrayId = (string) $tray->id();

        $residentes = [
            $this->sembrarEspecimen('MEPN-RES-ESP-001', $this->clasificacionPapilionidae()),
            $this->sembrarEspecimen('MEPN-RES-ESP-002', $this->clasificacionPapilionidae()),
        ];
        $this->asignacionRepo->sincronizar($tray->id(), $residentes);
    }

    #[When('el curador reubica el espécimen al unit tray de destino')]
    public function elCuradorReubicaElEspecimenAlUnitTrayDeDestino(): void
    {
        $this->contexto->setActor(ActorRol::Curador, 'curador-001');
        $this->ejecutarReubicacionEspecimenes(confirmar: false);
    }

    #[When('el curador reubica los especímenes al unit tray de destino')]
    public function elCuradorReubicaLosEspecimenesAlUnitTrayDeDestino(): void
    {
        $this->contexto->setActor(ActorRol::Curador, 'curador-001');
        $this->ejecutarReubicacionEspecimenes(confirmar: false);
    }

    #[When('el visitante reubica el espécimen al unit tray de destino')]
    public function elVisitanteReubicaElEspecimenAlUnitTrayDeDestino(): void
    {
        $this->prepararDestinoVacioSiHaceFalta();
        $this->contexto->setActor(ActorRol::Visitante, $this->visitanteId);
        $this->ejecutarReubicacionEspecimenes(confirmar: false);
    }

    #[When('el visitante intenta reubicar el espécimen a otro unit tray')]
    public function elVisitanteIntentaReubicarElEspecimenAOtroUnitTray(): void
    {
        $this->prepararDestinoVacioSiHaceFalta();
        $this->contexto->setActor(ActorRol::Visitante, $this->visitanteId);
        $this->ejecutarReubicacionEspecimenes(confirmar: false);
    }

    #[Then('el espécimen queda asignado al unit tray de destino')]
    public function elEspecimenQuedaAsignadoAlUnitTrayDeDestino(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        $this->assertEspecimenEnTray($this->especimenIds[0], $this->destinoTrayId);
    }

    #[Then('todos los especímenes quedan asignados al unit tray de destino')]
    public function todosLosEspecimenesQuedanAsignadosAlUnitTrayDeDestino(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        foreach ($this->especimenIds as $especimenId) {
            $this->assertEspecimenEnTray($especimenId, $this->destinoTrayId);
        }
    }

    #[Then('el movimiento queda registrado en la trazabilidad del espécimen')]
    public function elMovimientoQuedaRegistradoEnLaTrazabilidadDelEspecimen(): void
    {
        $eventos = $this->eventoRepo->buscarPorAgregado('especimen', $this->especimenIds[0]);
        Assert::assertNotEmpty($eventos, 'Debe registrarse un movimiento de trazabilidad del espécimen');
        Assert::assertSame('especimen_reubicado', $eventos[0]->tipoEvento());
    }

    #[Then('se registra un movimiento en la trazabilidad por cada espécimen reubicado')]
    public function seRegistraUnMovimientoPorCadaEspecimenReubicado(): void
    {
        foreach ($this->especimenIds as $especimenId) {
            $eventos = $this->eventoRepo->buscarPorAgregado('especimen', $especimenId);
            Assert::assertNotEmpty($eventos, "Debe registrarse un movimiento para el espécimen {$especimenId}");
        }
    }

    #[Then('se advierte que el espécimen no pertenece taxonómicamente al unit tray de destino')]
    public function seAdvierteQueElEspecimenNoPerteneceTaxonomicamente(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'La advertencia taxonómica no debe lanzar excepción');
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue($this->ultimaRespuesta->requiereConfirmacion, 'Debe requerir confirmación');
        Assert::assertFalse($this->ultimaRespuesta->reubicado, 'No debe persistirse sin confirmar');
        Assert::assertContains('MEPN-REUB-001', $this->ultimaRespuesta->especimenesFueraDeLugar);
    }

    #[Then('si el curador cancela, el espécimen permanece en su unit tray de origen')]
    public function siElCuradorCancelaElEspecimenPermaneceEnOrigen(): void
    {
        // Cancelar = no confirmar: la respuesta del When dejó reubicado=false sin persistir,
        // así que el espécimen sigue en su unit tray de origen.
        Assert::assertFalse($this->ultimaRespuesta->reubicado, 'Al cancelar no debe persistirse');
        $this->assertEspecimenEnTray($this->especimenIds[0], $this->origenTrayId);
    }

    #[Then('si el curador confirma, el espécimen queda asignado al unit tray de destino')]
    public function siElCuradorConfirmaElEspecimenQuedaEnDestino(): void
    {
        // Confirmar: al reintentar con confirmar=true la reubicación se persiste.
        $this->ejecutarReubicacionEspecimenes(confirmar: true);

        Assert::assertNull($this->excepcionCapturada);
        Assert::assertTrue($this->ultimaRespuesta->reubicado, 'Al confirmar, la reubicación debe persistirse');
        $this->assertEspecimenEnTray($this->especimenIds[0], $this->destinoTrayId);
    }

    // ==========================================
    // Reubicación de un unit tray a otra caja
    // ==========================================

    #[Given('que existe un unit tray asignado a una caja de origen')]
    public function queExisteUnUnitTrayAsignadoAUnaCajaDeOrigen(): void
    {
        [, $tray] = $this->sembrarCajaConTray('CAJA-TRAY-ORIGEN-001');
        $this->trayReubicadoId = (string) $tray->id();
    }

    #[Given('existe una caja de destino con espacio disponible')]
    public function existeUnaCajaDeDestinoConEspacioDisponible(): void
    {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde('CAJA-TRAY-DESTINO-001'),
        );
        $this->cajaRepo->guardar($caja);
        $this->destinoCajaId = (string) $caja->id();
    }

    #[When('el curador reubica el unit tray a la caja de destino')]
    public function elCuradorReubicaElUnitTrayALaCajaDeDestino(): void
    {
        $this->contexto->setActor(ActorRol::Curador, 'curador-001');

        try {
            $this->ultimaRespuesta = $this->reubicarUnitTrayHandler->handle(
                new ReubicarUnitTrayInput(
                    unitTrayId: $this->trayReubicadoId,
                    cajaDestinoId: $this->destinoCajaId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el unit tray queda asignado a la caja de destino')]
    public function elUnitTrayQuedaAsignadoALaCajaDeDestino(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );

        $tray = $this->unitTrayRepo->buscarPorId(UnitTrayId::desde($this->trayReubicadoId));
        Assert::assertNotNull($tray);
        Assert::assertSame($this->destinoCajaId, (string) $tray->cajaId(), 'El unit tray debe apuntar a la caja de destino');
    }

    #[Then('el movimiento queda registrado en la trazabilidad del unit tray')]
    public function elMovimientoQuedaRegistradoEnLaTrazabilidadDelUnitTray(): void
    {
        $eventos = $this->eventoRepo->buscarPorAgregado('unit_tray', $this->trayReubicadoId);
        Assert::assertNotEmpty($eventos, 'Debe registrarse un movimiento de trazabilidad del unit tray');
        Assert::assertSame('unit_tray_reubicado', $eventos[0]->tipoEvento());
    }

    // ==========================================
    // Autorización del visitante
    // ==========================================

    #[Given('que existe un visitante habilitado para reubicar')]
    public function queExisteUnVisitanteHabilitadoParaReubicar(): void
    {
        $this->visitanteId = $this->sembrarVisitante(habilitado: true);
    }

    #[Given('que existe un visitante sin la habilitación para reubicar')]
    public function queExisteUnVisitanteSinLaHabilitacionParaReubicar(): void
    {
        $this->visitanteId = $this->sembrarVisitante(habilitado: false);
    }

    #[Then('el movimiento queda registrado en la trazabilidad identificando al visitante como responsable')]
    public function elMovimientoIdentificaAlVisitanteComoResponsable(): void
    {
        $eventos = $this->eventoRepo->buscarPorAgregado('especimen', $this->especimenIds[0]);
        Assert::assertNotEmpty($eventos);
        $evento = $eventos[0];
        Assert::assertSame(ActorRol::Visitante, $evento->actorRol());
        Assert::assertSame($this->visitanteId, $evento->actorId(), 'El responsable del movimiento debe ser el visitante');
    }

    #[Then('la reubicación es rechazada')]
    public function laReubicacionEsRechazada(): void
    {
        Assert::assertInstanceOf(
            ReubicacionNoAutorizadaException::class,
            $this->excepcionCapturada,
            'La reubicación de un visitante sin habilitación debe rechazarse'
        );
    }

    #[Then('el espécimen permanece en su unit tray de origen')]
    public function elEspecimenPermaneceEnSuUnitTrayDeOrigen(): void
    {
        $this->assertEspecimenEnTray($this->especimenIds[0], $this->origenTrayId);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function ejecutarReubicacionEspecimenes(bool $confirmar): void
    {
        $this->excepcionCapturada = null;

        try {
            $this->ultimaRespuesta = $this->reubicarEspecimenesHandler->handle(
                new ReubicarEspecimenesInput(
                    destinoUnitTrayId: $this->destinoTrayId,
                    especimenIds: $this->especimenIds,
                    confirmar: $confirmar,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    private function prepararDestinoVacioSiHaceFalta(): void
    {
        if ($this->destinoTrayId !== null) {
            return;
        }
        [, $tray] = $this->sembrarCajaConTray('CAJA-DESTINO-VIS-001');
        $this->destinoTrayId = (string) $tray->id();
    }

    /** @return array{0: Caja, 1: UnitTray} */
    private function sembrarCajaConTray(string $codigoCaja, bool $esEspecial = false): array
    {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde($codigoCaja),
            esEspecial: $esEspecial,
            observacion: $esEspecial ? 'Caja especial de prueba' : null,
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

    private function sembrarEspecimen(string $codigoCatalogo, ClasificacionTaxonomica $clasificacion): string
    {
        $taxonId = (string) $this->taxonRepo->nextIdentity();
        $this->clasificacionPort->registrar($taxonId, $clasificacion);

        $especimen = Especimen::crear(
            id: $this->especimenRepo->nextIdentity(),
            codigoCatalogo: $codigoCatalogo,
            taxonId: $taxonId,
            localidad: 'Napo, Ecuador',
            fechaColecta: '2021-05-10',
            colector: 'Equipo MEPN',
        );
        $this->especimenRepo->guardar($especimen);

        return (string) $especimen->id();
    }

    private function sembrarVisitante(bool $habilitado): string
    {
        $visitante = Visitante::crear(
            id: $this->visitanteRepo->nextIdentity(),
            nombre: 'Visitante de prueba',
            contacto: null,
            registradoEn: new \DateTimeImmutable,
        );
        if ($habilitado) {
            $visitante->definirReubicacion(true);
        }
        $this->visitanteRepo->guardar($visitante);

        return (string) $visitante->id();
    }

    private function assertEspecimenEnTray(string $especimenId, ?string $trayId): void
    {
        $asignado = $this->asignacionRepo->unitTrayDeEspecimen($especimenId);
        Assert::assertNotNull($asignado, 'El espécimen debe estar asignado a un unit tray');
        Assert::assertSame($trayId, (string) $asignado, 'El espécimen debe estar en el unit tray esperado');
    }

    private function clasificacionNymphalidae(): ClasificacionTaxonomica
    {
        return ClasificacionTaxonomica::desde(
            orden: 'Lepidoptera',
            familia: 'Nymphalidae',
            genero: 'Danaus',
            especie: 'plexippus',
        );
    }

    private function clasificacionPapilionidae(): ClasificacionTaxonomica
    {
        return ClasificacionTaxonomica::desde(
            orden: 'Lepidoptera',
            familia: 'Papilionidae',
            genero: 'Papilio',
            especie: 'machaon',
        );
    }
}
