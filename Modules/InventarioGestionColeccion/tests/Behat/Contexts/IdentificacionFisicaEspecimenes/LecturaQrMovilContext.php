<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\IdentificacionFisicaEspecimenes;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryCodigoQrRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use PHPUnit\Framework\Assert;

final class LecturaQrMovilContext extends BaseContext
{
    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Especimen $especimenExistente = null;

    private ?string $payloadQr = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        // Repositorios en memoria por escenario. Los handlers se resuelven dentro de
        // cada paso (no en el constructor) para usar siempre estas mismas instancias,
        // aun cuando otros contextos de la suite reescriban los bindings al construirse.
        self::$app->instance(TaxonRepositoryInterface::class, new InMemoryTaxonRepository);
        self::$app->instance(EspecimenRepositoryInterface::class, new InMemoryEspecimenRepository);
        self::$app->instance(CodigoQrRepositoryInterface::class, new InMemoryCodigoQrRepository);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarEspecimenConQr(): Especimen
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

        $respuestaQr = $this->make(GenerarCodigoQrHandler::class)->handle(
            new GenerarCodigoQrInput(especimenId: (string) $especimen->id())
        );
        $this->payloadQr = $respuestaQr->payload;
        $this->especimenExistente = $especimen;

        return $especimen;
    }

    // =========================================================================
    // ESCENARIO: Resolver QR válido a la ficha del espécimen
    // =========================================================================

    #[Given('que existe un espécimen con su código QR generado')]
    public function queExisteUnEspecimenConSuCodigoQrGenerado(): void
    {
        $especimen = $this->sembrarEspecimenConQr();

        Assert::assertNotNull($this->payloadQr, 'El payload del QR generado no debe ser nulo');
        Assert::assertNotEmpty($this->payloadQr, 'El payload del QR generado no debe estar vacío');

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen no fue encontrado en el repositorio');
    }

    #[When('se resuelve el QR con el payload correcto del espécimen')]
    public function seResuелveElQrConElPayloadCorrectoDelEspecimen(): void
    {
        Assert::assertNotNull($this->payloadQr, 'Se esperaba un payload de QR del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->make(ResolverCodigoQrHandler::class)->handle(
                new ResolverCodigoQrInput(payload: $this->payloadQr)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se retorna la ficha digital completa del espécimen')]
    public function seRetornaLaFichaDigitalCompletaDelEspecimen(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotEmpty($this->ultimaRespuesta->id, 'La ficha debe incluir el ID del espécimen');
        Assert::assertNotEmpty($this->ultimaRespuesta->codigoCatalogo, 'La ficha debe incluir el código catálogo');
        Assert::assertSame(
            (string) $this->especimenExistente->id(),
            $this->ultimaRespuesta->id,
            'La ficha retornada debe corresponder al espécimen vinculado al QR'
        );
    }

    // =========================================================================
    // ESCENARIO: QR con payload inválido retorna error
    // =========================================================================

    #[Given('que se intenta resolver un QR con un payload que no corresponde a ningún espécimen')]
    public function queSeIntentaResolverUnQrConUnPayloadQueNoCorrespondeANingunEspecimen(): void
    {
        $this->payloadQr = 'payload-invalido-que-no-existe-en-el-sistema';

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimenes = $repo->buscarTodos();
        foreach ($especimenes as $especimen) {
            Assert::assertNotSame(
                $this->payloadQr,
                (string) $especimen->id(),
                'El payload inválido no debe coincidir con ningún ID de espécimen en el sistema'
            );
        }
    }

    #[When('se procesa el payload del QR inválido')]
    public function seProcesamosElPayloadDelQrInvalido(): void
    {
        Assert::assertNotNull($this->payloadQr, 'Se esperaba un payload del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->make(ResolverCodigoQrHandler::class)->handle(
                new ResolverCodigoQrInput(payload: $this->payloadQr)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema retorna un error indicando que el espécimen no fue encontrado')]
    public function elSistemaRetornaUnErrorIndicandoQueElEspecimenNoFueEncontrado(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que la resolución fallara por payload inválido pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'espécimen',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe mencionar que el espécimen no fue encontrado'
        );
    }
}
