<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarComposicionGabinete\ConsultarComposicionGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarComposicionGabinete\ConsultarComposicionGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja\ConsultarContenidoCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja\ConsultarContenidoCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray\ConsultarContenidoUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray\ConsultarContenidoUnitTrayInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete\ConsultarOcupacionGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete\ConsultarOcupacionGabineteInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryCajaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryGabineteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryRanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayRepository;
use PHPUnit\Framework\Assert;

/**
 * Cubre las consultas de curador del feature mapa_ubicacion_guia_especimenes:
 * composición y ocupación de un gabinete, contenido de una caja y de un unit tray.
 * Los escenarios del visitante (localización con visibilidad pública) se implementan
 * aparte cuando se retome la subtarea de ubicación física para visitantes.
 */
final class MapaUbicacionGuiaEspecimenesContext extends BaseContext
{
    private InMemoryGabineteRepository $gabineteRepo;

    private InMemoryRanuraGabineteRepository $ranuraRepo;

    private InMemoryCajaRepository $cajaRepo;

    private InMemoryUnitTrayRepository $unitTrayRepo;

    private InMemoryUnitTrayEspecimenRepository $asignacionRepo;

    private InMemoryEspecimenRepository $especimenRepo;

    private InMemoryTaxonRepository $taxonRepo;

    private ConsultarComposicionGabineteHandler $composicionHandler;

    private ConsultarOcupacionGabineteHandler $ocupacionHandler;

    private ConsultarContenidoCajaHandler $contenidoCajaHandler;

    private ConsultarContenidoUnitTrayHandler $contenidoUnitTrayHandler;

    private ?string $gabineteId = null;

    private ?string $cajaId = null;

    private ?string $unitTrayId = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    public function __construct()
    {
        $this->gabineteRepo = new InMemoryGabineteRepository;
        $this->ranuraRepo = new InMemoryRanuraGabineteRepository;
        $this->cajaRepo = new InMemoryCajaRepository;
        $this->unitTrayRepo = new InMemoryUnitTrayRepository;
        $this->asignacionRepo = new InMemoryUnitTrayEspecimenRepository;
        $this->especimenRepo = new InMemoryEspecimenRepository;
        $this->taxonRepo = new InMemoryTaxonRepository;

        self::$app->instance(GabineteRepository::class, $this->gabineteRepo);
        self::$app->instance(RanuraGabineteRepository::class, $this->ranuraRepo);
        self::$app->instance(CajaRepository::class, $this->cajaRepo);
        self::$app->instance(UnitTrayRepository::class, $this->unitTrayRepo);
        self::$app->instance(UnitTrayEspecimenRepository::class, $this->asignacionRepo);
        self::$app->instance(EspecimenRepositoryInterface::class, $this->especimenRepo);
        self::$app->instance(TaxonRepositoryInterface::class, $this->taxonRepo);

        $this->composicionHandler = $this->make(ConsultarComposicionGabineteHandler::class);
        $this->ocupacionHandler = $this->make(ConsultarOcupacionGabineteHandler::class);
        $this->contenidoCajaHandler = $this->make(ConsultarContenidoCajaHandler::class);
        $this->contenidoUnitTrayHandler = $this->make(ConsultarContenidoUnitTrayHandler::class);
    }

    // ==========================================
    // Composición taxonómica de un gabinete
    // ==========================================

    #[Given('que existe un gabinete con cajas clasificadas en sus ranuras')]
    public function queExisteUnGabineteConCajasClasificadasEnSusRanuras(): void
    {
        $this->gabineteId = $this->sembrarGabineteConCajas([
            ClasificacionTaxonomica::desde(familia: 'Formicidae', subfamilia: 'Myrmicinae', genero: 'Atta'),
            ClasificacionTaxonomica::desde(familia: 'Formicidae', subfamilia: 'Ponerinae', genero: 'Paraponera'),
        ]);
    }

    #[When('el curador consulta la composición del gabinete')]
    public function elCuradorConsultaLaComposicionDelGabinete(): void
    {
        Assert::assertNotNull($this->gabineteId, 'Se requiere un gabinete sembrado (@Given)');

        try {
            $this->ultimaRespuesta = $this->composicionHandler->handle(
                new ConsultarComposicionGabineteInput(gabineteId: $this->gabineteId)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se obtienen las subfamilias y géneros presentes en el gabinete')]
    public function seObtienenLasSubfamiliasYGenerosPresentesEnElGabinete(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción al consultar la composición');
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');

        Assert::assertEqualsCanonicalizing(
            ['Myrmicinae', 'Ponerinae'],
            $this->ultimaRespuesta->subfamilias,
            'Deben listarse las subfamilias distintas presentes en el gabinete'
        );
        Assert::assertEqualsCanonicalizing(
            ['Atta', 'Paraponera'],
            $this->ultimaRespuesta->generos,
            'Deben listarse los géneros distintos presentes en el gabinete'
        );
    }

    // ==========================================
    // Ocupación de las ranuras de un gabinete
    // ==========================================

    #[Given('que existe un gabinete con cajas ubicadas en sus ranuras')]
    public function queExisteUnGabineteConCajasUbicadasEnSusRanuras(): void
    {
        $this->gabineteId = $this->sembrarGabineteConCajas([
            ClasificacionTaxonomica::desde(familia: 'Formicidae', subfamilia: 'Myrmicinae', genero: 'Atta'),
            ClasificacionTaxonomica::desde(familia: 'Formicidae', subfamilia: 'Ponerinae', genero: 'Paraponera'),
        ]);
    }

    #[When('el curador consulta la ocupación del gabinete')]
    public function elCuradorConsultaLaOcupacionDelGabinete(): void
    {
        Assert::assertNotNull($this->gabineteId, 'Se requiere un gabinete sembrado (@Given)');

        try {
            $this->ultimaRespuesta = $this->ocupacionHandler->handle(
                new ConsultarOcupacionGabineteInput(gabineteId: $this->gabineteId)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se obtiene, para cada ranura, la caja que la ocupa y su clasificación')]
    public function seObtieneParaCadaRanuraLaCajaQueLaOcupaYSuClasificacion(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción al consultar la ocupación');
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');

        Assert::assertCount(2, $this->ultimaRespuesta->items, 'Debe haber un registro por ranura del gabinete');

        foreach ($this->ultimaRespuesta->items as $item) {
            Assert::assertTrue($item->ocupada, 'Cada ranura sembrada debe estar ocupada');
            Assert::assertNotNull($item->cajaId, 'Debe resolverse la caja que ocupa la ranura');
            Assert::assertNotNull($item->codigoCaja, 'Debe resolverse el código de la caja');
            Assert::assertSame('en_gabinete', $item->estado, 'La caja ubicada debe estar en gabinete');
            Assert::assertNotNull($item->clasificacion, 'Debe resolverse la clasificación de la caja');
            Assert::assertSame('Formicidae', $item->clasificacion['familia']);
        }
    }

    // ==========================================
    // Contenido de una caja
    // ==========================================

    #[Given('que existe una caja con unit trays asignados')]
    public function queExisteUnaCajaConUnitTraysAsignados(): void
    {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde('CAJA-MAP-001'),
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaId = (string) $caja->id();

        $this->sembrarUnitTray($caja, ClasificacionTaxonomica::desde(subfamilia: 'Myrmicinae', genero: 'Atta'));
        $this->sembrarUnitTray($caja, ClasificacionTaxonomica::desde(subfamilia: 'Ponerinae', genero: 'Paraponera'));
    }

    #[When('el curador consulta el contenido de la caja')]
    public function elCuradorConsultaElContenidoDeLaCaja(): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja sembrada (@Given)');

        try {
            $this->ultimaRespuesta = $this->contenidoCajaHandler->handle(
                new ConsultarContenidoCajaInput(cajaId: $this->cajaId)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se obtienen sus unit trays con la clasificación dominante de cada uno')]
    public function seObtienenSusUnitTraysConLaClasificacionDominanteDeCadaUno(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción al consultar el contenido de la caja');
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');

        Assert::assertCount(2, $this->ultimaRespuesta->items, 'Deben listarse los unit trays de la caja');

        foreach ($this->ultimaRespuesta->items as $item) {
            Assert::assertNotNull($item->clasificacionDominante, 'Cada unit tray debe traer su clasificación dominante');
            Assert::assertArrayHasKey('genero', $item->clasificacionDominante);
        }
    }

    // ==========================================
    // Contenido de un unit tray
    // ==========================================

    #[Given('que existe un unit tray con especímenes asignados')]
    public function queExisteUnUnitTrayConEspecimenesAsignados(): void
    {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: CodigoCaja::desde('CAJA-MAP-002'),
        );
        $this->cajaRepo->guardar($caja);

        $unitTray = UnitTray::crear(
            id: $this->unitTrayRepo->nextIdentity(),
            cajaId: $caja->id(),
            numero: 1,
        );
        $this->unitTrayRepo->guardar($unitTray);
        $this->unitTrayId = (string) $unitTray->id();

        $especimenIds = [
            $this->sembrarEspecimen('MEPN-MAP-001', 'Atta cephalotes'),
            $this->sembrarEspecimen('MEPN-MAP-002', 'Paraponera clavata'),
        ];
        $this->asignacionRepo->sincronizar($unitTray->id(), $especimenIds);
    }

    #[When('el curador consulta el contenido del unit tray')]
    public function elCuradorConsultaElContenidoDelUnitTray(): void
    {
        Assert::assertNotNull($this->unitTrayId, 'Se requiere un unit tray sembrado (@Given)');

        try {
            $this->ultimaRespuesta = $this->contenidoUnitTrayHandler->handle(
                new ConsultarContenidoUnitTrayInput(unitTrayId: $this->unitTrayId)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se obtienen los especímenes con su nombre científico')]
    public function seObtienenLosEspecimenesConSuNombreCientifico(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción al consultar el contenido del unit tray');
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');

        Assert::assertCount(2, $this->ultimaRespuesta->items, 'Deben listarse los especímenes del unit tray');

        $nombres = array_map(fn ($item) => $item->nombreCientifico, $this->ultimaRespuesta->items);
        Assert::assertEqualsCanonicalizing(['Atta cephalotes', 'Paraponera clavata'], $nombres);
    }

    // ==========================================
    // Factory Methods
    // ==========================================

    /**
     * Crea un gabinete y ubica una caja clasificada en cada ranura.
     *
     * @param  ClasificacionTaxonomica[]  $clasificaciones  Una por ranura/caja a sembrar.
     */
    private function sembrarGabineteConCajas(array $clasificaciones): string
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: CodigoGabinete::desde('GAB-MAP-001'),
            nombre: 'Gabinete Mapa',
            totalRanuras: count($clasificaciones),
        );
        $this->gabineteRepo->guardar($gabinete);

        $numero = 1;
        foreach ($clasificaciones as $clasificacion) {
            $ranura = RanuraGabinete::crear(
                id: $this->ranuraRepo->nextIdentity(),
                gabineteId: $gabinete->id(),
                numeroRanura: $numero,
            );

            $caja = Caja::crear(
                id: $this->cajaRepo->nextIdentity(),
                codigo: CodigoCaja::desde('CAJA-MAP-G'.$numero),
                clasificacionTaxonomica: $clasificacion,
            );
            $caja->ingresarEnRanura($ranura->id());
            $caja->pullEvents();
            $this->cajaRepo->guardar($caja);

            $ranura->asignarCaja($caja->id());
            $this->ranuraRepo->guardar($ranura);

            $numero++;
        }

        return (string) $gabinete->id();
    }

    private function sembrarUnitTray(Caja $caja, ClasificacionTaxonomica $clasificacionDominante): void
    {
        $unitTray = UnitTray::crear(
            id: $this->unitTrayRepo->nextIdentity(),
            cajaId: $caja->id(),
            numero: $this->unitTrayRepo->siguienteNumero($caja->id()),
            clasificacionDominante: $clasificacionDominante,
        );
        $this->unitTrayRepo->guardar($unitTray);
    }

    private function sembrarEspecimen(string $codigoCatalogo, string $nombreCientifico): string
    {
        $taxon = Taxon::crear(
            id: $this->taxonRepo->nextIdentity(),
            nombreCientifico: $nombreCientifico,
            rango: 'especie',
        );
        $this->taxonRepo->guardar($taxon);

        $especimen = Especimen::crear(
            id: $this->especimenRepo->nextIdentity(),
            codigoCatalogo: $codigoCatalogo,
            taxonId: (string) $taxon->id(),
            localidad: 'Napo, Ecuador',
            fechaColecta: '2021-05-10',
            colector: 'Equipo MEPN',
        );
        $this->especimenRepo->guardar($especimen);

        return (string) $especimen->id();
    }
}
