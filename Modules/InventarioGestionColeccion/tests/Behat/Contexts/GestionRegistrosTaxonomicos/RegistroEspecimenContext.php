<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use PHPUnit\Framework\Assert;

final class RegistroEspecimenContext extends BaseContext
{
    private RegistrarEspecimenHandler $registrarHandler;

    private ?Taxon $taxonExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    public function __construct()
    {
        $taxonRepo = new InMemoryTaxonRepository;
        $especimenRepo = new InMemoryEspecimenRepository;
        $entidadRepo = new InMemoryEntidadDepositanteRepository;

        self::$app->instance(TaxonRepositoryInterface::class, $taxonRepo);
        self::$app->instance(EspecimenRepositoryInterface::class, $especimenRepo);
        self::$app->instance(EntidadDepositanteRepositoryInterface::class, $entidadRepo);

        $this->registrarHandler = $this->make(RegistrarEspecimenHandler::class);
    }

    // =========================================================================
    // ESCENARIO: Registrar un especímen con datos válidos
    // =========================================================================

    #[Given('que existe un taxón registrado en el sistema para asociar al especímen')]
    public function queExisteUnTaxonRegistradoParaAsociar(): void
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
    }

    #[When('el curador registra el especímen con código :codigo y localidad :localidad')]
    public function elCuradorRegistraElEspecimen(string $codigo, string $localidad): void
    {
        Assert::assertNotNull($this->taxonExistente, 'Se requiere un taxón existente para el registro');

        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarEspecimenInput(
                    codigoCatalogo: $codigo,
                    taxonId: (string) $this->taxonExistente->id(),
                    localidad: $localidad,
                    fechaColecta: '2024-03-15',
                    colector: 'Dr. Investigador',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el especímen queda registrado con estado disponible')]
    public function elEspecimenQuedaRegistradoConEstadoDisponible(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = $repo->buscarPorId($this->ultimaRespuesta->id);
        Assert::assertNotNull($especimen, 'El especímen no fue encontrado en el repositorio');
        Assert::assertTrue(
            $especimen->estado()->equals(EstadoEspecimen::Disponible),
            "Se esperaba estado 'disponible', se obtuvo: {$especimen->estado()->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Código de catálogo duplicado
    // =========================================================================

    #[Given('que ya existe un especímen con código de catálogo :codigo')]
    public function queYaExisteUnEspecimenConCodigo(string $codigo): void
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
        $this->taxonExistente = $taxon;

        $especimenRepo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = Especimen::crear(
            id: $especimenRepo->nextIdentity(),
            codigoCatalogo: $codigo,
            taxonId: (string) $taxon->id(),
            localidad: 'Pichincha',
            fechaColecta: '2024-01-01',
            colector: 'Colector Inicial',
        );
        $especimenRepo->guardar($especimen);
    }

    #[When('el curador intenta registrar otro especímen con el mismo código :codigo')]
    public function elCuradorIntentaRegistrarOtroEspecimenConElMismoCodigo(string $codigo): void
    {
        Assert::assertNotNull($this->taxonExistente, 'Se requiere un taxón existente');

        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarEspecimenInput(
                    codigoCatalogo: $codigo,
                    taxonId: (string) $this->taxonExistente->id(),
                    localidad: 'Imbabura',
                    fechaColecta: '2024-06-01',
                    colector: 'Otro Colector',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza el registro con error de código duplicado')]
    public function elSistemaRechazaElRegistroConErrorDeCodigoDuplicado(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el registro fallara por código duplicado pero completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'duplicado',
            $this->excepcionCapturada->getMessage(),
        );
    }

    // =========================================================================
    // ESCENARIO: Taxón inexistente
    // =========================================================================

    #[Given('que no existe ningún taxón en el sistema')]
    public function queNoExisteNingunTaxonEnElSistema(): void
    {
        // El InMemoryTaxonRepository arranca vacío — ninguna acción requerida.
    }

    #[When('el curador intenta registrar un especímen con un taxón inexistente')]
    public function elCuradorIntentaRegistrarUnEspecimenConTaxonInexistente(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarEspecimenInput(
                    codigoCatalogo: 'COLEOP-999',
                    taxonId: '00000000-0000-4000-8000-000000000000',
                    localidad: 'Quito',
                    fechaColecta: '2024-01-01',
                    colector: 'Colector',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza el registro indicando que el taxón no fue encontrado')]
    public function elSistemaRechazaElRegistroIndicandoTaxonNoEncontrado(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el registro fallara por taxón inexistente pero completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'taxón',
            $this->excepcionCapturada->getMessage(),
        );
    }
}
