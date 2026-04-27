<?php

declare(strict_types=1);

namespace Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\ValidacionYCalidadDatos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionInformacionTaxonomica\Application\UseCases\VerificarConsistenciaDocumental\VerificarConsistenciaDocumentalHandler;
use Modules\GestionInformacionTaxonomica\Application\UseCases\VerificarConsistenciaDocumental\VerificarConsistenciaDocumentalInput;
use Modules\GestionInformacionTaxonomica\Domain\Entities\Especimen;
use Modules\GestionInformacionTaxonomica\Domain\Entities\Taxon;
use Modules\GestionInformacionTaxonomica\Domain\Repositories\EspecimenRepositoryInterface;
use Modules\GestionInformacionTaxonomica\Domain\Repositories\TaxonRepositoryInterface;
use Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class VerificacionDocumentosLegalesContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private VerificarConsistenciaDocumentalHandler $verificarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Especimen $especimenExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->verificarHandler = $this->make(VerificarConsistenciaDocumentalHandler::class);
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

    private function sembrarEspecimenConsistente(): Especimen
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
            documentos: ['acta_donacion' => 'ACT-2020-001', 'permiso_colecta' => 'PERM-2020-005'],
        );
        $repo->guardar($especimen);
        $this->especimenExistente = $especimen;

        return $especimen;
    }

    private function sembrarEspecimenInconsistente(): Especimen
    {
        $taxon = $this->sembrarTaxonBase();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = Especimen::crear(
            id: $repo->nextIdentity(),
            codigoCatalogo: 'MEPN-002',
            taxonId: (string) $taxon->id(),
            localidad: 'Napo, Ecuador',
            fechaColecta: '2020-03-15',
            colector: 'Juan Pérez',
            documentos: ['acta_donacion' => 'ACT-INEXISTENTE-999'],
        );
        $repo->guardar($especimen);
        $this->especimenExistente = $especimen;

        return $especimen;
    }

    // =========================================================================
    // ESCENARIO: Documentos consistentes con el sistema no generan alertas
    // =========================================================================

    #[Given('que existe un espécimen con documentos registrados y datos consistentes en el sistema')]
    public function queExisteUnEspecimenConDocumentosRegistradosYDatosConsistentesEnElSistema(): void
    {
        $especimen = $this->sembrarEspecimenConsistente();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen consistente no fue encontrado en el repositorio');
        Assert::assertNotEmpty($persistido->codigoCatalogo(), 'El espécimen debe tener código catálogo');
    }

    #[When('se ejecuta la verificación de consistencia documental')]
    public function seEjecutaLaVerificacionDeConsistenciaDocumental(): void
    {
        Assert::assertNotNull($this->especimenExistente, 'Se esperaba un espécimen del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->verificarHandler->handle(
                new VerificarConsistenciaDocumentalInput(
                    especimenId: (string) $this->especimenExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la verificación concluye sin inconsistencias')]
    public function laVerificacionConcluyeSinInconsistencias(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->consistente,
            'Se esperaba que la verificación concluyera sin inconsistencias'
        );
        Assert::assertEmpty(
            $this->ultimaRespuesta->inconsistencias,
            'No se esperaban inconsistencias en un espécimen con datos consistentes'
        );
    }

    // =========================================================================
    // ESCENARIO: Documentos inconsistentes con el sistema generan una alerta
    // =========================================================================

    #[Given('que existe un espécimen con datos que no coinciden con sus documentos físicos')]
    public function queExisteUnEspecimenConDatosQueNoCoicidenConSusDocumentosFisicos(): void
    {
        $especimen = $this->sembrarEspecimenInconsistente();

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId($especimen->id());
        Assert::assertNotNull($persistido, 'El espécimen inconsistente no fue encontrado en el repositorio');
    }

    #[Then('el sistema genera una alerta de inconsistencia documental')]
    public function elSistemaGeneraUnaAlertaDeInconsistenciaDocumental(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada en lugar de retornar una alerta: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertFalse(
            $this->ultimaRespuesta->consistente,
            'Se esperaba que la verificación detectara inconsistencias'
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->inconsistencias,
            'Se esperaba al menos una inconsistencia reportada'
        );
    }
}
