<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\UseCases\RegistrarTaxon\RegistrarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\UseCases\RegistrarTaxon\RegistrarTaxonInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class UnicidadNomenclaturaContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarTaxonHandler $registrarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Taxon $taxonExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->registrarHandler = $this->make(RegistrarTaxonHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarTaxonConNombreYRango(string $nombre, string $rango): Taxon
    {
        $repo = $this->make(TaxonRepositoryInterface::class);
        $taxon = Taxon::crear(
            id: $repo->nextIdentity(),
            nombreCientifico: $nombre,
            rango: $rango,
            autor: 'Autor Canónico',
            anioDescripcion: 1850,
        );

        $repo->guardar($taxon);
        $this->taxonExistente = $taxon;

        return $taxon;
    }

    // =========================================================================
    // ESCENARIO: No se puede crear un taxon con nombre científico duplicado
    // =========================================================================

    #[Given('que existe un taxon con nombre científico :nombre en el rango :rango')]
    public function queExisteUnTaxonConNombreCientificoEnElRango(string $nombre, string $rango): void
    {
        $taxon = $this->sembrarTaxonConNombreYRango($nombre, $rango);

        $repo = $this->make(TaxonRepositoryInterface::class);
        $persistido = $repo->buscarPorId($taxon->id());
        Assert::assertNotNull($persistido, 'El taxon base no fue encontrado en el repositorio tras guardar');
        Assert::assertSame($nombre, $persistido->nombreCientifico(), 'El nombre científico persistido no coincide');
        Assert::assertSame($rango, $persistido->rango()->value, 'El rango persistido no coincide');
    }

    #[When('el curador intenta crear otro taxon con el mismo nombre y rango')]
    public function elCuradorIntentaCrearOtroTaxonConElMismoNombreYRango(): void
    {
        Assert::assertNotNull($this->taxonExistente, 'Se esperaba un taxon existente del step Dado anterior');

        $nombreDuplicado = $this->taxonExistente->nombreCientifico();
        $rangoDuplicado = $this->taxonExistente->rango()->value;

        Assert::assertNotEmpty($nombreDuplicado, 'El nombre a duplicar no debe estar vacío');
        Assert::assertNotEmpty($rangoDuplicado, 'El rango a duplicar no debe estar vacío');

        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarTaxonInput(
                    nombreCientifico: $nombreDuplicado,
                    rango: $rangoDuplicado,
                    autor: 'Otro Autor',
                    anioDescripcion: 1900,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza el registro indicando duplicado de nomenclatura')]
    public function elSistemaRechazaElRegistroIndicandoDuplicadoDeNomenclatura(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el registro fallara por nombre duplicado pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'duplicado',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe indicar que el nombre está duplicado'
        );
    }

    // =========================================================================
    // ESCENARIO: Se puede crear con el mismo nombre en diferente rango
    // =========================================================================

    #[When('el curador crea un taxon con el mismo nombre pero en el rango :rangoNuevo')]
    public function elCuradorCreaUnTaxonConElMismoNombrePeroEnElRango(string $rangoNuevo): void
    {
        Assert::assertNotNull($this->taxonExistente, 'Se esperaba un taxon existente del step Dado anterior');

        $mismoNombre = $this->taxonExistente->nombreCientifico();
        Assert::assertNotSame(
            $rangoNuevo,
            $this->taxonExistente->rango()->value,
            'El rango nuevo debe ser distinto al del taxon existente para que este escenario tenga sentido'
        );

        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarTaxonInput(
                    nombreCientifico: $mismoNombre,
                    rango: $rangoNuevo,
                    autor: 'Autor Canónico',
                    anioDescripcion: 1850,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el taxon queda registrado exitosamente en el rango :rangoNuevo')]
    public function elTaxonQuedaRegistradoExitosamenteEnElRango(string $rangoNuevo): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'Se esperaba registro exitoso pero el handler lanzó: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame($rangoNuevo, $this->ultimaRespuesta->rango, "Se esperaba rango '{$rangoNuevo}'");
    }
}
