<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use PHPUnit\Framework\Assert;

final class ActualizarEspecimenContext extends BaseContext
{
    private ActualizarEspecimenHandler $actualizarHandler;

    private ?Especimen $especimenExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    public function __construct()
    {
        $especimenRepo = new InMemoryEspecimenRepository;
        $entidadRepo = new InMemoryEntidadDepositanteRepository;

        self::$app->instance(EspecimenRepositoryInterface::class, $especimenRepo);
        self::$app->instance(EntidadDepositanteRepositoryInterface::class, $entidadRepo);

        $this->actualizarHandler = $this->make(ActualizarEspecimenHandler::class);
    }

    // =========================================================================
    // ESCENARIO: El curador actualiza los datos de un especímen disponible
    // =========================================================================

    #[Given('que existe un especímen disponible con localidad :localidad')]
    public function queExisteUnEspecimenDisponibleConLocalidad(string $localidad): void
    {
        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = Especimen::crear(
            id: $repo->nextIdentity(),
            codigoCatalogo: 'COLEOP-001',
            taxonId: 'taxon-uuid-dummy-0000-000000000001',
            localidad: $localidad,
            fechaColecta: '2024-03-15',
            colector: 'Colector Original',
        );
        $repo->guardar($especimen);
        $this->especimenExistente = $especimen;

        Assert::assertSame(
            $localidad,
            $especimen->localidad(),
            "La localidad inicial debe ser '{$localidad}'"
        );
    }

    #[When('el curador actualiza el especímen con localidad :localidad y colector :colector')]
    public function elCuradorActualizaElEspecimen(string $localidad, string $colector): void
    {
        Assert::assertNotNull($this->especimenExistente, 'Se requiere un especímen existente del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->actualizarHandler->handle(
                new ActualizarEspecimenInput(
                    especimenId: (string) $this->especimenExistente->id(),
                    localidad: $localidad,
                    fechaColecta: '2025-01-01',
                    colector: $colector,
                    entidadDepositanteId: null,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el especímen refleja la localidad :localidad y el colector :colector')]
    public function elEspecimenReflejaLaLocalidadYElColector(string $localidad, string $colector): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame($localidad, $this->ultimaRespuesta->localidad);
        Assert::assertSame($colector, $this->ultimaRespuesta->colector);

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($this->especimenExistente->id());
        Assert::assertNotNull($persistido, 'El especímen debe existir en el repositorio');
        Assert::assertSame($localidad, $persistido->localidad());
        Assert::assertSame($colector, $persistido->colector());
    }

    // =========================================================================
    // ESCENARIO: El curador no puede actualizar un especímen que no existe
    // =========================================================================

    #[Given('que no existe ningún especímen en el sistema')]
    public function queNoExisteNingunEspecimenEnElSistema(): void
    {
        // El InMemoryEspecimenRepository arranca vacío — ninguna acción requerida.
    }

    #[When('el curador intenta actualizar un especímen inexistente')]
    public function elCuradorIntentaActualizarUnEspecimenInexistente(): void
    {
        try {
            $this->ultimaRespuesta = $this->actualizarHandler->handle(
                new ActualizarEspecimenInput(
                    especimenId: '00000000-0000-4000-8000-000000000000',
                    localidad: 'Quito',
                    fechaColecta: '2024-01-01',
                    colector: 'Colector',
                    entidadDepositanteId: null,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema informa que el especímen no fue encontrado')]
    public function elSistemaInformaQueEspecimenNoFueEncontrado(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que la operación fallara por especímen inexistente pero completó sin error'
        );
        Assert::assertInstanceOf(
            \DomainException::class,
            $this->excepcionCapturada,
            'Se esperaba una DomainException'
        );
    }
}
