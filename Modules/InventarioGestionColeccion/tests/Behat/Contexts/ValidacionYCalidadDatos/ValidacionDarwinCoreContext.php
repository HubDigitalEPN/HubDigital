<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\ValidacionYCalidadDatos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\UseCases\ValidarDarwinCore\ValidarDarwinCoreHandler;
use Modules\InventarioGestionColeccion\Application\UseCases\ValidarDarwinCore\ValidarDarwinCoreInput;
use Modules\InventarioGestionColeccion\Domain\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class ValidacionDarwinCoreContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private ValidarDarwinCoreHandler $validarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Especimen $especimenBajoValidacion = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->validarHandler = $this->make(ValidarDarwinCoreHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarTaxonBase(): Taxon
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

        return $taxon;
    }

    private function sembrarEspecimenCompleto(): Especimen
    {
        $taxon = $this->sembrarTaxonBase();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = Especimen::crear(
            id: $repo->nextIdentity(),
            codigoCatalogo: 'MEPN-001',
            taxonId: (string) $taxon->id(),
            localidad: 'Napo, Ecuador',
            fechaColecta: '2020-03-15',
            colector: 'Juan Pérez',
        );
        $repo->guardar($especimen);
        $this->especimenBajoValidacion = $especimen;

        return $especimen;
    }

    // =========================================================================
    // ESQUEMA: Validación de campo obligatorio Darwin Core ausente
    // =========================================================================

    #[Given('que existe un espécimen con el campo :campo vacío')]
    public function queExisteUnEspecimenConElCampoVacio(string $campo): void
    {
        $taxon = $this->sembrarTaxonBase();
        $repo = $this->make(EspecimenRepositoryInterface::class);

        $args = [
            'id' => $repo->nextIdentity(),
            'codigoCatalogo' => 'MEPN-001',
            'taxonId' => (string) $taxon->id(),
            'localidad' => 'Napo, Ecuador',
            'fechaColecta' => '2020-03-15',
            'colector' => 'Juan Pérez',
        ];

        $mapaVaciado = [
            'codigo_catalogo' => 'codigoCatalogo',
            'taxon_id' => 'taxonId',
            'localidad' => 'localidad',
            'fecha_colecta' => 'fechaColecta',
            'colector' => 'colector',
        ];

        Assert::assertArrayHasKey(
            $campo,
            $mapaVaciado,
            "El campo '{$campo}' no está mapeado en el helper de vaciado"
        );

        $args[$mapaVaciado[$campo]] = '';

        $especimen = Especimen::crear(
            id: $args['id'],
            codigoCatalogo: $args['codigoCatalogo'],
            taxonId: $args['taxonId'],
            localidad: $args['localidad'],
            fechaColecta: $args['fechaColecta'],
            colector: $args['colector'],
        );
        $repo->guardar($especimen);
        $this->especimenBajoValidacion = $especimen;

        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen con campo vaciado no fue encontrado en el repositorio');
    }

    #[When('se ejecuta la validación Darwin Core sobre el espécimen')]
    public function seEjecutaLaValidacionDarwinCoreSobreElEspecimen(): void
    {
        Assert::assertNotNull($this->especimenBajoValidacion, 'Se esperaba un espécimen del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->validarHandler->handle(
                new ValidarDarwinCoreInput(
                    especimenId: (string) $this->especimenBajoValidacion->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema genera un error de validación indicando que :campo es obligatorio')]
    public function elSistemaGeneraUnErrorDeValidacionIndicandoQueCampoEsObligatorio(string $campo): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            "Se esperaba que la validación fallara por campo '{$campo}' vacío pero el handler completó sin error"
        );
        Assert::assertStringContainsStringIgnoringCase(
            str_replace('_', ' ', $campo),
            $this->excepcionCapturada->getMessage(),
            "El mensaje de error debe mencionar el campo '{$campo}' como obligatorio"
        );
    }

    // =========================================================================
    // ESCENARIO: Espécimen con todos los campos obligatorios pasa la validación
    // =========================================================================

    #[Given('que existe un espécimen con todos los campos obligatorios Darwin Core completos')]
    public function queExisteUnEspecimenConTodosLosCamposObligatoriosDarwinCoreCompletos(): void
    {
        $especimen = $this->sembrarEspecimenCompleto();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen completo no fue encontrado en el repositorio');
        Assert::assertNotEmpty($persistido->codigoCatalogo(), 'codigoCatalogo no debe estar vacío');
        Assert::assertNotEmpty((string) $persistido->taxonId(), 'taxonId no debe estar vacío');
        Assert::assertNotEmpty($persistido->localidad(), 'localidad no debe estar vacío');
        Assert::assertNotEmpty($persistido->fechaColecta(), 'fechaColecta no debe estar vacío');
        Assert::assertNotEmpty($persistido->colector(), 'colector no debe estar vacío');
    }

    #[Then('el espécimen pasa la validación sin errores')]
    public function elEspecimenPasaLaValidacionSinErrores(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'Se esperaba validación exitosa pero el handler lanzó: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->valido,
            'Se esperaba que el espécimen pasara la validación Darwin Core'
        );
        Assert::assertEmpty(
            $this->ultimaRespuesta->errores,
            'No se esperaban errores en un espécimen con todos los campos completos'
        );
    }
}
