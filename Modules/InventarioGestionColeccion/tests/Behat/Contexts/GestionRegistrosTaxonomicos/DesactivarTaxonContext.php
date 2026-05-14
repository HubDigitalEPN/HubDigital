<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarTaxon\DesactivarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarTaxon\DesactivarTaxonInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use PHPUnit\Framework\Assert;

final class DesactivarTaxonContext extends BaseContext
{
    private DesactivarTaxonHandler $desactivarHandler;

    private ?Taxon $taxonExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    public function __construct()
    {
        $taxonRepo = new InMemoryTaxonRepository;
        self::$app->instance(TaxonRepositoryInterface::class, $taxonRepo);

        $this->desactivarHandler = $this->make(DesactivarTaxonHandler::class);
    }

    // =========================================================================
    // ESCENARIO: El curador desactiva un taxón activo
    // =========================================================================

    #[Given('que existe un taxón activo en el árbol taxonómico')]
    public function queExisteUnTaxonActivoEnElArbol(): void
    {
        $repo = $this->make(TaxonRepositoryInterface::class);
        $taxon = Taxon::crear(
            id: $repo->nextIdentity(),
            nombreCientifico: 'Morpho peleides',
            rango: 'especie',
            autor: 'Kollar',
            anioDescripcion: 1850,
        );
        $repo->guardar($taxon);
        $this->taxonExistente = $taxon;

        Assert::assertTrue(
            $taxon->estado()->equals(EstadoTaxon::Activo),
            'El taxón debe estar activo al inicio del escenario'
        );
    }

    #[When('el curador desactiva el taxón')]
    public function elCuradorDesactivaElTaxon(): void
    {
        Assert::assertNotNull($this->taxonExistente, 'Se requiere un taxón existente del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->desactivarHandler->handle(
                new DesactivarTaxonInput(taxonId: (string) $this->taxonExistente->id())
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el taxón queda en estado inactivo')]
    public function elTaxonQuedaEnEstadoInactivo(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame('inactivo', $this->ultimaRespuesta->estado);

        $repo = $this->make(TaxonRepositoryInterface::class);
        $persistido = $repo->buscarPorId($this->taxonExistente->id());
        Assert::assertNotNull($persistido, 'El taxón debe existir en el repositorio');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoTaxon::Inactivo),
            "Se esperaba estado 'inactivo', se obtuvo: {$persistido->estado()->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: El curador no puede desactivar un taxón que no existe
    // =========================================================================

    #[Given('que no existe el taxón en el árbol taxonómico')]
    public function queNoExisteElTaxonEnElArbol(): void
    {
        // El InMemoryTaxonRepository arranca vacío — ninguna acción requerida.
    }

    #[When('el curador intenta desactivar un taxón inexistente')]
    public function elCuradorIntentaDesactivarUnTaxonInexistente(): void
    {
        try {
            $this->ultimaRespuesta = $this->desactivarHandler->handle(
                new DesactivarTaxonInput(taxonId: '00000000-0000-4000-8000-000000000000')
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema informa que el taxón no fue encontrado')]
    public function elSistemaInformaQueTaxonNoFueEncontrado(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que la operación fallara por taxón inexistente pero completó sin error'
        );
        Assert::assertInstanceOf(
            \DomainException::class,
            $this->excepcionCapturada,
            'Se esperaba una DomainException'
        );
    }
}
