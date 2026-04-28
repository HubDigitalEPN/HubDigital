<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\InteroperabilidadEstados;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\UseCases\ProcesarEventoEspecimenPrestado\ProcesarEventoEspecimenPrestadoHandler;
use Modules\InventarioGestionColeccion\Application\UseCases\ProcesarEventoEspecimenPrestado\ProcesarEventoEspecimenPrestadoInput;
use Modules\InventarioGestionColeccion\Domain\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class SincronizacionEstadoPrestamoContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private ProcesarEventoEspecimenPrestadoHandler $procesarEventoHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Especimen $especimenExistente = null;

    private ?string $identificadorEvento = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->procesarEventoHandler = $this->make(ProcesarEventoEspecimenPrestadoHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarEspecimenDisponible(): Especimen
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
        );
        $repo->guardar($especimen);
        $this->especimenExistente = $especimen;
        $this->identificadorEvento = (string) $especimen->id();

        return $especimen;
    }

    // =========================================================================
    // ESCENARIO: Espécimen cambia a "prestado" al recibir el evento EspecimenPrestado
    // =========================================================================

    #[Given('que existe un espécimen en estado disponible')]
    public function queExisteUnEspecimenEnEstadoDisponible(): void
    {
        $especimen = $this->sembrarEspecimenDisponible();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen no fue encontrado en el repositorio');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoEspecimen::Disponible),
            "Se esperaba estado 'disponible', se obtuvo: {$persistido->estado()->value}"
        );
    }

    #[When('el sistema recibe el evento EspecimenPrestado para ese espécimen')]
    public function elSistemaRecibeElEventoEspecimenPrestadoParaEseEspecimen(): void
    {
        Assert::assertNotNull($this->identificadorEvento, 'Se esperaba un identificador de espécimen del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->procesarEventoHandler->handle(
                new ProcesarEventoEspecimenPrestadoInput(
                    especimenId: $this->identificadorEvento,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el espécimen queda en estado prestado')]
    public function elEspecimenQuedaEnEstadoPrestado(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($this->identificadorEvento);
        Assert::assertNotNull($persistido, 'El espécimen no fue encontrado en el repositorio tras el evento');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoEspecimen::Prestado),
            "Se esperaba estado 'prestado' tras el evento, se obtuvo: {$persistido->estado()->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: No se procesa el evento si el espécimen no existe en el sistema
    // =========================================================================

    #[Given('que no existe ningún espécimen con el identificador del evento')]
    public function queNoExisteNingunEspecimenConElIdentificadorDelEvento(): void
    {
        $this->identificadorEvento = 'guid-inexistente-que-no-esta-en-el-sistema';

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($this->identificadorEvento);
        Assert::assertNull($persistido, 'No debe existir ningún espécimen con el identificador de prueba');
    }

    #[When('el sistema recibe el evento EspecimenPrestado para ese identificador')]
    public function elSistemaRecibeElEventoEspecimenPrestadoParaEseIdentificador(): void
    {
        Assert::assertNotNull($this->identificadorEvento, 'Se esperaba un identificador del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->procesarEventoHandler->handle(
                new ProcesarEventoEspecimenPrestadoInput(
                    especimenId: $this->identificadorEvento,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema registra un error indicando que el espécimen no fue encontrado')]
    public function elSistemaRegistraUnErrorIndicandoQueElEspecimenNoFueEncontrado(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el procesamiento fallara por espécimen inexistente pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'espécimen',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe indicar que el espécimen no fue encontrado'
        );
    }
}
