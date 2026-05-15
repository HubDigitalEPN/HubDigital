<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\IdentificacionFisicaEspecimenes;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class GeneracionCodigoQrContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private GenerarCodigoQrHandler $generarQrHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Especimen $especimenExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->generarQrHandler = $this->make(GenerarCodigoQrHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarEspecimenSinQr(): Especimen
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

        return $especimen;
    }

    private function sembrarEspecimenConQr(): Especimen
    {
        $especimen = $this->sembrarEspecimenSinQr();

        $this->generarQrHandler->handle(
            new GenerarCodigoQrInput(especimenId: (string) $especimen->id())
        );

        return $especimen;
    }

    // =========================================================================
    // ESCENARIO: Generar código QR para un espécimen que aún no tiene QR
    // =========================================================================

    #[Given('que existe un espécimen registrado con GUID y sin código QR')]
    public function queExisteUnEspecimenRegistradoConGuidYSinCodigoQr(): void
    {
        $especimen = $this->sembrarEspecimenSinQr();

        $qrRepo = $this->make(CodigoQrRepositoryInterface::class);
        $qr = $qrRepo->buscarPorEspecimen((string) $especimen->id());
        Assert::assertNull($qr, 'El espécimen no debe tener un código QR antes de esta prueba');
    }

    #[When('el curador solicita la generación del código QR del espécimen')]
    public function elCuradorSolicitaLaGeneracionDelCodigoQrDelEspecimen(): void
    {
        Assert::assertNotNull($this->especimenExistente, 'Se esperaba un espécimen existente del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->generarQrHandler->handle(
                new GenerarCodigoQrInput(
                    especimenId: (string) $this->especimenExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el código QR queda generado y vinculado al espécimen')]
    public function elCodigoQrQuedaGeneradoYVinculadoAlEspecimen(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotEmpty($this->ultimaRespuesta->codigoQrId, 'Se esperaba un identificador de QR generado');

        $qrRepo = $this->make(CodigoQrRepositoryInterface::class);
        $qr = $qrRepo->buscarPorEspecimen((string) $this->especimenExistente->id());
        Assert::assertNotNull($qr, 'El código QR no fue encontrado en el repositorio tras su generación');
        Assert::assertSame(
            (string) $this->especimenExistente->id(),
            (string) $qr->especimenId(),
            'El QR debe estar vinculado al espécimen correcto'
        );
    }

    // =========================================================================
    // ESCENARIO: No se genera un segundo QR si el espécimen ya tiene uno
    // =========================================================================

    #[Given('que existe un espécimen registrado con su código QR ya generado')]
    public function queExisteUnEspecimenRegistradoConSuCodigoQrYaGenerado(): void
    {
        $especimen = $this->sembrarEspecimenConQr();

        $qrRepo = $this->make(CodigoQrRepositoryInterface::class);
        $qr = $qrRepo->buscarPorEspecimen((string) $especimen->id());
        Assert::assertNotNull($qr, 'El espécimen debe tener ya un código QR en este escenario');
    }

    #[When('el curador solicita generar un nuevo código QR para el mismo espécimen')]
    public function elCuradorSolicitaGenerarUnNuevoCodigoQrParaElMismoEspecimen(): void
    {
        Assert::assertNotNull($this->especimenExistente, 'Se esperaba un espécimen existente del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->generarQrHandler->handle(
                new GenerarCodigoQrInput(
                    especimenId: (string) $this->especimenExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza la generación indicando que el espécimen ya tiene un QR')]
    public function elSistemaRechazaLaGeneracionIndicandoQueElEspecimenYaTieneUnQr(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que la generación fallara por QR existente pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'qr',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe mencionar que el espécimen ya tiene un QR'
        );
    }
}
