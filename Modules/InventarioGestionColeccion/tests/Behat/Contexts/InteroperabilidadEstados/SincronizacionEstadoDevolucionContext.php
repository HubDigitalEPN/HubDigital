<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\InteroperabilidadEstados;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEspecimenDevuelto\ProcesarEventoEspecimenDevueltoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEspecimenDevuelto\ProcesarEventoEspecimenDevueltoInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class SincronizacionEstadoDevolucionContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private ProcesarEventoEspecimenDevueltoHandler $procesarEventoHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Especimen $especimenExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->procesarEventoHandler = $this->make(ProcesarEventoEspecimenDevueltoHandler::class);
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

    private function sembrarEspecimenEnEstado(EstadoEspecimen $estado): Especimen
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
            estado: $estado,
        );
        $repo->guardar($especimen);
        $this->especimenExistente = $especimen;

        return $especimen;
    }

    // =========================================================================
    // ESCENARIO: Espécimen vuelve a "disponible" al recibir el evento EspecimenDevuelto
    // =========================================================================

    #[Given('que existe un espécimen en estado prestado')]
    public function queExisteUnEspecimenEnEstadoPrestado(): void
    {
        $especimen = $this->sembrarEspecimenEnEstado(EstadoEspecimen::Prestado);

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen no fue encontrado en el repositorio');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoEspecimen::Prestado),
            "Se esperaba estado 'prestado', se obtuvo: {$persistido->estado()->value}"
        );
    }

    #[When('el sistema recibe el evento EspecimenDevuelto para ese espécimen')]
    public function elSistemaRecibeElEventoEspecimenDevueltoParaEseEspecimen(): void
    {
        Assert::assertNotNull($this->especimenExistente, 'Se esperaba un espécimen del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->procesarEventoHandler->handle(
                new ProcesarEventoEspecimenDevueltoInput(
                    especimenId: (string) $this->especimenExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el espécimen queda en estado disponible')]
    public function elEspecimenQuedaEnEstadoDisponible(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId((string) $this->especimenExistente->id());
        Assert::assertNotNull($persistido, 'El espécimen no fue encontrado en el repositorio tras el evento');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoEspecimen::Disponible),
            "Se esperaba estado 'disponible' tras el evento, se obtuvo: {$persistido->estado()->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: No se actualiza si el espécimen ya está disponible
    // =========================================================================

    #[Then('el sistema rechaza la transición indicando que el espécimen ya está disponible')]
    public function elSistemaRechazaLaTransicionIndicandoQueElEspecimenYaEstaDisponible(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que la transición fallara por espécimen ya disponible pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'disponible',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe indicar que el espécimen ya está disponible'
        );
    }
}
