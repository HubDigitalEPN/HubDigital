<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\IdentificacionFisicaEspecimenes;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use PHPUnit\Framework\Assert;

final class AsignacionGuidEspecimenContext extends BaseContext
{
    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Especimen $especimenExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private array $datosEspecimenValidos = [
        'codigoCatalogo' => 'MEPN-001',
        'localidad' => 'Napo, Ecuador',
        'fechaColecta' => '2020-03-15',
        'colector' => 'Juan Pérez',
    ];

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        // Repositorios en memoria por escenario. Los handlers se resuelven dentro de
        // cada paso (no en el constructor) para usar siempre estas mismas instancias,
        // aun cuando otros contextos de la suite reescriban los bindings al construirse.
        self::$app->instance(TaxonRepositoryInterface::class, new InMemoryTaxonRepository);
        self::$app->instance(EspecimenRepositoryInterface::class, new InMemoryEspecimenRepository);
        self::$app->instance(EntidadDepositanteRepositoryInterface::class, new InMemoryEntidadDepositanteRepository);
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

        return $entidad;
    }

    private function sembrarEspecimenBase(): Especimen
    {
        $taxon = $this->sembrarTaxonBase();
        $entidad = $this->sembrarEntidadBase();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = Especimen::crear(
            id: $repo->nextIdentity(),
            codigoCatalogo: $this->datosEspecimenValidos['codigoCatalogo'],
            taxonId: (string) $taxon->id(),
            localidad: $this->datosEspecimenValidos['localidad'],
            fechaColecta: $this->datosEspecimenValidos['fechaColecta'],
            colector: $this->datosEspecimenValidos['colector'],
            entidadDepositanteId: (string) $entidad->id(),
        );
        $repo->guardar($especimen);
        $this->especimenExistente = $especimen;

        return $especimen;
    }

    // =========================================================================
    // ESCENARIO: Asignar GUID al registrar un nuevo espécimen
    // =========================================================================

    #[Given('que el curador tiene los datos completos de un nuevo espécimen')]
    public function queElCuradorTieneLosDataCompletosDeUnNuevoEspecimen(): void
    {
        Assert::assertNotEmpty($this->datosEspecimenValidos['codigoCatalogo'], 'codigoCatalogo es requerido');
        Assert::assertNotEmpty($this->datosEspecimenValidos['localidad'], 'localidad es requerido');
        Assert::assertNotEmpty($this->datosEspecimenValidos['fechaColecta'], 'fechaColecta es requerido');
        Assert::assertNotEmpty($this->datosEspecimenValidos['colector'], 'colector es requerido');
    }

    #[When('el curador registra el espécimen en el sistema')]
    public function elCuradorRegistraElEspecimenEnElSistema(): void
    {
        $taxon = $this->sembrarTaxonBase();
        $entidad = $this->sembrarEntidadBase();

        try {
            $this->ultimaRespuesta = $this->make(RegistrarEspecimenHandler::class)->handle(
                new RegistrarEspecimenInput(
                    codigoCatalogo: $this->datosEspecimenValidos['codigoCatalogo'],
                    taxonId: (string) $taxon->id(),
                    localidad: $this->datosEspecimenValidos['localidad'],
                    fechaColecta: $this->datosEspecimenValidos['fechaColecta'],
                    colector: $this->datosEspecimenValidos['colector'],
                    entidadDepositanteId: (string) $entidad->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el espécimen queda registrado con un GUID único asignado')]
    public function elEspecimenQuedaRegistradoConUnGuidUnicoAsignado(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotEmpty($this->ultimaRespuesta->id, 'El espécimen debe tener un GUID asignado');

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($this->ultimaRespuesta->id);
        Assert::assertNotNull($persistido, 'El espécimen no fue encontrado en el repositorio tras su registro');
        Assert::assertNotEmpty((string) $persistido->id(), 'El GUID del espécimen persistido no debe estar vacío');
    }

    // =========================================================================
    // ESCENARIO: Dos especímenes no pueden compartir el mismo GUID
    // =========================================================================

    #[Given('que existe un espécimen registrado con su GUID asignado')]
    public function queExisteUnEspecimenRegistradoConSuGuidAsignado(): void
    {
        $especimen = $this->sembrarEspecimenBase();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen no fue encontrado en el repositorio tras guardar');
        Assert::assertNotEmpty((string) $persistido->id(), 'El espécimen debe tener un GUID asignado');
    }

    #[When('el sistema intenta registrar un segundo espécimen con el mismo GUID')]
    public function elSistemaIntentaRegistrarUnSegundoEspecimenConElMismoGuid(): void
    {
        Assert::assertNotNull($this->especimenExistente, 'Se esperaba un espécimen existente del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->make(RegistrarEspecimenHandler::class)->handle(
                new RegistrarEspecimenInput(
                    codigoCatalogo: 'MEPN-002',
                    taxonId: (string) $this->especimenExistente->taxonId(),
                    localidad: 'Pichincha, Ecuador',
                    fechaColecta: '2021-06-01',
                    colector: 'Ana López',
                    entidadDepositanteId: (string) $this->especimenExistente->entidadDepositanteId(),
                    guidForzado: (string) $this->especimenExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza el registro indicando GUID duplicado')]
    public function elSistemaRechazaElRegistroIndicandoGuidDuplicado(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el registro fallara por GUID duplicado pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'guid',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe indicar que el GUID está duplicado'
        );
    }
}
