<?php

declare(strict_types=1);

namespace Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionInformacionTaxonomica\Application\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\GestionInformacionTaxonomica\Application\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\GestionInformacionTaxonomica\Domain\Entities\Especimen;
use Modules\GestionInformacionTaxonomica\Domain\Entities\Taxon;
use Modules\GestionInformacionTaxonomica\Domain\Repositories\EspecimenRepositoryInterface;
use Modules\GestionInformacionTaxonomica\Domain\Repositories\TaxonRepositoryInterface;
use Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class BusquedaArbolTaxonomicoContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private BuscarEspecimenesHandler $buscarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->buscarHandler = $this->make(BuscarEspecimenesHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarEspecimenConTaxon(): Especimen
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

        $especimenRepo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = Especimen::crear(
            id: $especimenRepo->nextIdentity(),
            codigoCatalogo: 'MEPN-001',
            taxonId: (string) $taxon->id(),
            localidad: 'Napo, Ecuador',
            fechaColecta: '2020-03-15',
            colector: 'Juan Pérez',
        );
        $especimenRepo->guardar($especimen);

        return $especimen;
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Búsqueda por criterio taxonómico
    // =========================================================================

    #[Given('que existen especímenes registrados en el sistema')]
    public function queExistenEspecimenesRegistradosEnElSistema(): void
    {
        $especimen = $this->sembrarEspecimenConTaxon();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen sembrado no fue encontrado en el repositorio');
        Assert::assertNotEmpty($persistido->codigoCatalogo(), 'El espécimen debe tener codigo_catalogo');
    }

    #[When('se busca por :criterio con el valor :valor')]
    public function seBuscaPorCriterioConElValor(string $criterio, string $valor): void
    {
        try {
            $this->ultimaRespuesta = $this->buscarHandler->handle(
                new BuscarEspecimenesInput(
                    criterio: $criterio,
                    valor: $valor,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se retornan los registros que coinciden con la búsqueda')]
    public function seRetornanLosRegistrosQueCoinciden(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertIsArray($this->ultimaRespuesta->items, 'Se esperaba un array de resultados');
        Assert::assertNotEmpty($this->ultimaRespuesta->items, 'La búsqueda debería retornar al menos un resultado');
    }

    // =========================================================================
    // ESCENARIO: Búsqueda sin resultados
    // =========================================================================

    #[When('se busca por taxon con el valor :valor')]
    public function seBuscaPorTaxonConElValor(string $valor): void
    {
        try {
            $this->ultimaRespuesta = $this->buscarHandler->handle(
                new BuscarEspecimenesInput(
                    criterio: 'taxon',
                    valor: $valor,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema retorna una lista vacía sin error')]
    public function elSistemaRetornaUnaListaVaciaSinError(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'Se esperaba respuesta vacía sin error, pero el handler lanzó: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertIsArray($this->ultimaRespuesta->items, 'Se esperaba un array de resultados');
        Assert::assertEmpty($this->ultimaRespuesta->items, 'Se esperaba una lista vacía para búsqueda sin coincidencias');
    }
}
