<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\UseCases\ActualizarTaxon\ActualizarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\UseCases\ActualizarTaxon\ActualizarTaxonInput;
use Modules\InventarioGestionColeccion\Application\UseCases\RegistrarTaxon\RegistrarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\UseCases\RegistrarTaxon\RegistrarTaxonInput;
use Modules\InventarioGestionColeccion\Domain\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class RegistroTaxonContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarTaxonHandler $registrarHandler;

    private ActualizarTaxonHandler $actualizarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Taxon $taxonExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private array $datosTaxonValidos = [
        'nombre_cientifico' => 'Morpho peleides',
        'rango' => 'especie',
        'autor' => 'Kollar',
        'anio_descripcion' => 1850,
    ];

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->registrarHandler = $this->make(RegistrarTaxonHandler::class);
        $this->actualizarHandler = $this->make(ActualizarTaxonHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarTaxonBase(): Taxon
    {
        $repo = $this->make(TaxonRepositoryInterface::class);
        $taxon = Taxon::crear(
            id: $repo->nextIdentity(),
            nombreCientifico: $this->datosTaxonValidos['nombre_cientifico'],
            rango: $this->datosTaxonValidos['rango'],
            autor: $this->datosTaxonValidos['autor'],
            anioDescripcion: $this->datosTaxonValidos['anio_descripcion'],
        );

        $repo->guardar($taxon);
        $this->taxonExistente = $taxon;

        return $taxon;
    }

    // =========================================================================
    // ESCENARIO: Crear un nuevo taxon en el árbol taxonómico
    // =========================================================================

    #[Given('que el curador tiene los datos de un nuevo taxon')]
    public function queElCuradorTieneLosDataDeUnNuevoTaxon(): void
    {
        Assert::assertNotEmpty($this->datosTaxonValidos['nombre_cientifico'], 'nombre_cientifico es requerido');
        Assert::assertNotEmpty($this->datosTaxonValidos['rango'], 'rango es requerido');
        Assert::assertNotEmpty($this->datosTaxonValidos['autor'], 'autor es requerido');
        Assert::assertGreaterThan(0, $this->datosTaxonValidos['anio_descripcion'], 'anio_descripcion debe ser positivo');
    }

    #[When('el curador registra el taxon')]
    public function elCuradorRegistraElTaxon(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarTaxonInput(
                    nombreCientifico: $this->datosTaxonValidos['nombre_cientifico'],
                    rango: $this->datosTaxonValidos['rango'],
                    autor: $this->datosTaxonValidos['autor'],
                    anioDescripcion: $this->datosTaxonValidos['anio_descripcion'],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el taxon queda registrado en estado activo')]
    public function elTaxonQuedaRegistradoEnEstadoActivo(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(TaxonRepositoryInterface::class);
        $taxon = $repo->buscarPorId($this->ultimaRespuesta->id);
        Assert::assertNotNull($taxon, 'El taxon no fue encontrado en el repositorio tras su registro');
        Assert::assertTrue(
            $taxon->estado()->equals(EstadoTaxon::Activo),
            "Se esperaba estado 'activo', se obtuvo: {$taxon->estado()->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Editar un taxon existente
    // =========================================================================

    #[Given('que existe un taxon registrado en el sistema')]
    public function queExisteUnTaxonRegistradoEnElSistema(): void
    {
        $taxon = $this->sembrarTaxonBase();

        $repo = $this->make(TaxonRepositoryInterface::class);
        $persistido = $repo->buscarPorId($taxon->id());
        Assert::assertNotNull($persistido, 'El taxon no fue encontrado en el repositorio tras guardar');
        Assert::assertNotEmpty($persistido->nombreCientifico(), 'nombre_cientifico no debe estar vacío');
    }

    #[When('el curador actualiza los datos del taxon')]
    public function elCuradorActualizaLosDatosDelTaxon(): void
    {
        Assert::assertNotNull($this->taxonExistente, 'Se esperaba un taxon existente del step Dado anterior');

        $nuevoNombre = 'Morpho menelaus';
        $nuevoAutor = 'Linnaeus';

        Assert::assertNotSame(
            $nuevoNombre,
            $this->taxonExistente->nombreCientifico(),
            'El nuevo nombre debe ser distinto al original para que la actualización sea significativa'
        );

        try {
            $this->ultimaRespuesta = $this->actualizarHandler->handle(
                new ActualizarTaxonInput(
                    taxonId: (string) $this->taxonExistente->id(),
                    nombreCientifico: $nuevoNombre,
                    autor: $nuevoAutor,
                    anioDescripcion: 1758,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el taxon refleja la información actualizada')]
    public function elTaxonReflejaLaInformacionActualizada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame('Morpho menelaus', $this->ultimaRespuesta->nombreCientifico);
        Assert::assertSame('Linnaeus', $this->ultimaRespuesta->autor);
        Assert::assertSame(1758, $this->ultimaRespuesta->anioDescripcion);
    }

    // =========================================================================
    // ESCENARIO: No se puede crear un taxon con rango inválido
    // =========================================================================

    #[Given('que el curador tiene datos de un taxon con rango inválido')]
    public function queElCuradorTieneDatosDeUnTaxonConRangoInvalido(): void
    {
        $this->datosTaxonValidos['rango'] = 'rango_que_no_existe';

        Assert::assertNotEmpty($this->datosTaxonValidos['nombre_cientifico'], 'Los demás datos son válidos');
        Assert::assertSame('rango_que_no_existe', $this->datosTaxonValidos['rango'], 'El rango debe ser inválido');
    }

    #[Then('el sistema rechaza el registro con un error de validación de rango')]
    public function elSistemaRechazaElRegistroConUnErrorDeValidacionDeRango(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el registro fallara por rango inválido pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'rango',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe mencionar el rango inválido'
        );
    }
}
