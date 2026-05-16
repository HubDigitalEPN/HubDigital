<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeneradorActaPdfPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes\FakeGeneradorActaPdfAdapter;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use PHPUnit\Framework\Assert;

final class GeneracionActaEntregaContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private GenerarActaEntregaHandler $generarActaHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?EntidadDepositante $entidadExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $taxonRepo = new InMemoryTaxonRepository;
        $especimenRepo = new InMemoryEspecimenRepository;
        $entidadRepo = new InMemoryEntidadDepositanteRepository;
        $fakePdf = new FakeGeneradorActaPdfAdapter;

        self::$app->instance(TaxonRepositoryInterface::class, $taxonRepo);
        self::$app->instance(EspecimenRepositoryInterface::class, $especimenRepo);
        self::$app->instance(EntidadDepositanteRepositoryInterface::class, $entidadRepo);
        self::$app->instance(GeneradorActaPdfPort::class, $fakePdf);

        $this->generarActaHandler = $this->make(GenerarActaEntregaHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarEntidadBase(): EntidadDepositante
    {
        $repo = $this->make(EntidadDepositanteRepositoryInterface::class);
        $entidad = EntidadDepositante::crear(
            id: $repo->nextIdentity(),
            nombre: 'Escuela Politécnica Nacional',
            tipo: 'institucion',
            contacto: 'coleccion@epn.edu.ec',
        );

        $repo->guardar($entidad);
        $this->entidadExistente = $entidad;

        return $entidad;
    }

    private function sembrarEspecimenParaEntidad(string $entidadId): Especimen
    {
        $taxonRepo = $this->make(TaxonRepositoryInterface::class);
        $taxon = Taxon::crear(
            id: $taxonRepo->nextIdentity(),
            nombreCientifico: 'Morpho peleides',
            rango: 'especie',
            autor: 'Kollar',
            anioDescripcion: 1850,
        );
        $taxonRepo->guardar($taxon);

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = Especimen::crear(
            id: $repo->nextIdentity(),
            codigoCatalogo: 'MEPN-001',
            taxonId: (string) $taxon->id(),
            localidad: 'Napo, Ecuador',
            fechaColecta: '2020-03-15',
            colector: 'Juan Pérez',
            entidadDepositanteId: $entidadId,
        );
        $repo->guardar($especimen);

        return $especimen;
    }

    // =========================================================================
    // ESCENARIO: Generar acta de entrega para una entidad con especímenes
    // =========================================================================

    #[Given('que existe una entidad depositante con especímenes registrados en estado disponible')]
    public function queExisteUnaEntidadDepositanteConEspecimenesEnEstadoDisponible(): void
    {
        $entidad = $this->sembrarEntidadBase();
        $especimen = $this->sembrarEspecimenParaEntidad((string) $entidad->id());

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen vinculado no fue encontrado en el repositorio');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoEspecimen::Disponible),
            'El espécimen debe estar en estado disponible antes de generar el acta'
        );
    }

    #[When('el curador solicita la generación del acta de entrega')]
    public function elCuradorSolicitudLaGeneracionDelActaDeEntrega(): void
    {
        Assert::assertNotNull($this->entidadExistente, 'Se esperaba una entidad existente del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->generarActaHandler->handle(
                new GenerarActaEntregaInput(
                    entidadDepositanteId: (string) $this->entidadExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el acta de entrega es generada y queda disponible para descarga')]
    public function elActaDeEntregaEsGeneradaYQuedaDisponibleParaDescarga(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->pdfRuta,
            'Se esperaba una ruta de PDF generado en la respuesta'
        );
    }

    // =========================================================================
    // ESCENARIO: No se puede generar acta si la entidad no tiene especímenes
    // =========================================================================

    #[Given('que existe una entidad depositante sin especímenes registrados')]
    public function queExisteUnaEntidadDepositanteSinEspecimenesRegistrados(): void
    {
        $entidad = $this->sembrarEntidadBase();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimenes = $repo->buscarPorEntidadDepositante((string) $entidad->id());
        Assert::assertEmpty($especimenes, 'La entidad no debe tener especímenes vinculados en este escenario');
    }

    #[Then('el sistema rechaza la generación indicando que no hay especímenes')]
    public function elSistemaRechazaLaGeneracionIndicandoQueNoHayEspecimenes(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que la generación fallara por ausencia de especímenes pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'espécimen',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe mencionar la ausencia de especímenes'
        );
    }
}
