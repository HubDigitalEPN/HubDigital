<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\IdentificacionFisicaEspecimenes;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IdentificarEspecimen\IdentificarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IdentificarEspecimen\IdentificarEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryCodigoQrRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryIdentificacionRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use PHPUnit\Framework\Assert;

final class LecturaQrMovilContext extends BaseContext
{
    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Especimen $especimenExistente = null;

    private ?string $payloadQr = null;

    private ?string $codigoQrIdOriginal = null;

    private ?Especimen $segundoEspecimen = null;

    private ?string $payloadSegundoQr = null;

    private ?ResolverCodigoQrOutput $fichaSegundoEspecimen = null;

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

    private function sembrarEspecimenConQr(bool $sinDeterminar = false): Especimen
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
            taxonId: $sinDeterminar ? null : (string) $taxon->id(),
            localidad: 'Napo, Ecuador',
            fechaColecta: '2020-03-15',
            colector: 'Juan Pérez',
        );
        $repo->guardar($especimen);

        $respuestaQr = $this->make(GenerarCodigoQrHandler::class)->handle(
            new GenerarCodigoQrInput(especimenId: (string) $especimen->id())
        );
        $this->payloadQr = $respuestaQr->payload;
        $this->codigoQrIdOriginal = $respuestaQr->codigoQrId;
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

    // Flujos entre corrección, identificación y resolución de la etiqueta.

    #[Given('que existe un espécimen sin determinar con su código QR generado')]
    public function existeUnEspecimenSinDeterminarConQr(): void
    {
        $this->sembrarEspecimenConQr(sinDeterminar: true);
    }

    #[Given('que existen dos especímenes con etiquetas QR independientes')]
    public function existenDosEspecimenesConQrIndependientes(): void
    {
        $primero = $this->sembrarEspecimenConQr();
        $repo = $this->make(EspecimenRepositoryInterface::class);
        $this->segundoEspecimen = Especimen::crear(
            id: $repo->nextIdentity(),
            codigoCatalogo: 'MEPN-002',
            taxonId: $primero->taxonId(),
            localidad: 'Pichincha, Ecuador',
            fechaColecta: '2021-04-20',
            colector: 'Ana Torres',
        );
        $repo->guardar($this->segundoEspecimen);
        $this->payloadSegundoQr = $this->make(GenerarCodigoQrHandler::class)->handle(
            new GenerarCodigoQrInput(especimenId: (string) $this->segundoEspecimen->id())
        )->payload;
    }

    #[When('el curador corrige la localidad a :localidad y el colector a :colector sin cambiar la etiqueta')]
    public function corregirDatosConLaMismaEtiqueta(string $localidad, string $colector): void
    {
        Assert::assertNotNull($this->especimenExistente);
        (new ActualizarEspecimenHandler(
            $this->make(EspecimenRepositoryInterface::class),
            new InMemoryEntidadDepositanteRepository,
        ))->handle(new ActualizarEspecimenInput(
            especimenId: (string) $this->especimenExistente->id(),
            localidad: $localidad,
            fechaColecta: $this->especimenExistente->fechaColecta(),
            colector: $colector,
            entidadDepositanteId: null,
        ));
    }

    #[When('el curador identifica el espécimen etiquetado como :nombreCientifico')]
    public function identificarEspecimenEtiquetado(string $nombreCientifico): void
    {
        Assert::assertNotNull($this->especimenExistente);
        $taxones = $this->make(TaxonRepositoryInterface::class);
        $taxon = Taxon::crear($taxones->nextIdentity(), $nombreCientifico, 'especie');
        $taxones->guardar($taxon);
        (new IdentificarEspecimenHandler(
            new InMemoryIdentificacionRepository,
            $this->make(EspecimenRepositoryInterface::class),
            $taxones,
        ))->handle(new IdentificarEspecimenInput(
            especimenId: (string) $this->especimenExistente->id(),
            taxonId: (string) $taxon->id(),
            identificadoPor: 'Curadora de la colección',
        ));
    }

    #[When('el visitante escanea las dos etiquetas originales')]
    public function escanearDosEtiquetasOriginales(): void
    {
        Assert::assertNotNull($this->payloadQr);
        Assert::assertNotNull($this->payloadSegundoQr);
        $resolver = $this->make(ResolverCodigoQrHandler::class);
        $this->ultimaRespuesta = $resolver->handle(new ResolverCodigoQrInput($this->payloadQr));
        $this->fichaSegundoEspecimen = $resolver->handle(new ResolverCodigoQrInput($this->payloadSegundoQr));
    }

    private function fichaActual(): ResolverCodigoQrOutput
    {
        Assert::assertNull($this->excepcionCapturada, $this->excepcionCapturada?->getMessage() ?? '');
        Assert::assertInstanceOf(ResolverCodigoQrOutput::class, $this->ultimaRespuesta);

        return $this->ultimaRespuesta;
    }

    #[Then('la ficha del QR muestra la localidad :localidad y el colector :colector')]
    public function fichaMuestraDatosCorregidos(string $localidad, string $colector): void
    {
        $ficha = $this->fichaActual();
        Assert::assertSame($localidad, $ficha->localidad);
        Assert::assertSame($colector, $ficha->colector);
        Assert::assertSame($localidad, $ficha->datos['localidad']);
        Assert::assertSame($colector, $ficha->datos['colector']);
    }

    #[Then('la etiqueta conserva el mismo espécimen, código de catálogo y payload')]
    public function etiquetaConservaSuIdentidad(): void
    {
        Assert::assertNotNull($this->especimenExistente);
        $ficha = $this->fichaActual();
        Assert::assertSame((string) $this->especimenExistente->id(), $ficha->id);
        Assert::assertSame($this->especimenExistente->codigoCatalogo(), $ficha->codigoCatalogo);
        $qr = $this->make(CodigoQrRepositoryInterface::class)->buscarPorEspecimen($ficha->id);
        Assert::assertNotNull($qr);
        Assert::assertSame($this->payloadQr, $qr->payload());
        Assert::assertSame($this->codigoQrIdOriginal, (string) $qr->id());
    }

    #[Then('la ficha resuelta desde la misma etiqueta muestra el taxón :nombreCientifico')]
    public function fichaMuestraTaxonActual(string $nombreCientifico): void
    {
        $ficha = $this->fichaActual();
        Assert::assertSame($nombreCientifico, $ficha->taxonNombre);
        Assert::assertSame($nombreCientifico, $ficha->datos['taxonNombre']);
    }

    #[Then('la ficha del QR todavía no presenta una identificación taxonómica')]
    public function fichaSinIdentificacionTaxonomica(): void
    {
        $ficha = $this->fichaActual();
        Assert::assertNull($ficha->taxonId);
        Assert::assertNull($ficha->taxonNombre);
        Assert::assertNull($ficha->datos['taxonNombre']);
    }

    #[Then('la segunda etiqueta conserva la localidad :localidad y el colector :colector')]
    public function segundaEtiquetaNoMezclaLosDatos(string $localidad, string $colector): void
    {
        Assert::assertNotNull($this->segundoEspecimen);
        Assert::assertNotNull($this->fichaSegundoEspecimen);
        Assert::assertSame((string) $this->segundoEspecimen->id(), $this->fichaSegundoEspecimen->id);
        Assert::assertSame('MEPN-002', $this->fichaSegundoEspecimen->codigoCatalogo);
        Assert::assertSame($localidad, $this->fichaSegundoEspecimen->localidad);
        Assert::assertSame($colector, $this->fichaSegundoEspecimen->colector);
        Assert::assertNotSame($this->payloadQr, $this->payloadSegundoQr);
        Assert::assertNotSame($this->fichaActual()->id, $this->fichaSegundoEspecimen->id);
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
