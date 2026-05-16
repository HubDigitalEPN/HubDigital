<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\ValidacionYCalidadDatos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerReporteErroresEtl\ObtenerReporteErroresEtlHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerReporteErroresEtl\ObtenerReporteErroresEtlInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RegistroEtl;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RegistroEtlRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRegistroEtl;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class TrazabilidadErroresMigracionContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private ObtenerReporteErroresEtlHandler $reporteHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?string $loteId = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->reporteHandler = $this->make(ObtenerReporteErroresEtlHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarLoteConRechazados(): string
    {
        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $loteId = (string) $repo->nextLoteIdentity();

        $rechazado = RegistroEtl::crear(
            id: $repo->nextIdentity(),
            loteId: $loteId,
            nombreCientifico: '',
            fechaColecta: '2020-03-15',
            localidad: 'Napo, Ecuador',
            colector: 'Juan Pérez',
            datosOriginales: ['fuente' => 'base_historica_2010'],
        );
        $rechazado->marcarRechazado('nombre_cientifico es obligatorio y no puede estar vacío');
        $repo->guardar($rechazado);

        $this->loteId = $loteId;

        return $loteId;
    }

    private function sembrarLoteSinErrores(): string
    {
        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $loteId = (string) $repo->nextLoteIdentity();

        $migrado = RegistroEtl::crear(
            id: $repo->nextIdentity(),
            loteId: $loteId,
            nombreCientifico: 'Morpho peleides',
            fechaColecta: '2020-03-15',
            localidad: 'Napo, Ecuador',
            colector: 'Juan Pérez',
            datosOriginales: ['fuente' => 'base_historica_2010'],
        );
        $migrado->marcarMigrado();
        $repo->guardar($migrado);

        $this->loteId = $loteId;

        return $loteId;
    }

    // =========================================================================
    // ESCENARIO: Obtener reporte de registros rechazados en una migración
    // =========================================================================

    #[Given('que existe un lote ETL con registros en estado rechazado')]
    public function queExisteUnLoteEtlConRegistrosEnEstadoRechazado(): void
    {
        $loteId = $this->sembrarLoteConRechazados();

        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $registros = $repo->buscarPorLote($loteId);
        Assert::assertNotEmpty($registros, 'El lote debe contener al menos un registro');

        $hayRechazado = false;
        foreach ($registros as $registro) {
            if ($registro->estado()->equals(EstadoRegistroEtl::Rechazado)) {
                $hayRechazado = true;
                break;
            }
        }
        Assert::assertTrue($hayRechazado, 'El lote debe tener al menos un registro rechazado');
    }

    #[When('se solicita el reporte de errores del lote')]
    public function seSolicitaElReporteDeErroresDelLote(): void
    {
        Assert::assertNotNull($this->loteId, 'Se esperaba un loteId del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->reporteHandler->handle(
                new ObtenerReporteErroresEtlInput(loteId: $this->loteId)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el reporte incluye los registros rechazados con su causa de error')]
    public function elReporteIncluyeLosRegistrosRechazadosConSuCausaDeError(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertIsArray($this->ultimaRespuesta->rechazados, 'Se esperaba un array de registros rechazados');
        Assert::assertNotEmpty($this->ultimaRespuesta->rechazados, 'El reporte debe incluir al menos un registro rechazado');

        foreach ($this->ultimaRespuesta->rechazados as $rechazado) {
            Assert::assertNotEmpty($rechazado->id, 'Cada registro rechazado debe tener un ID');
            Assert::assertNotEmpty($rechazado->causaError, 'Cada registro rechazado debe tener una causa de error');
        }
    }

    // =========================================================================
    // ESCENARIO: Un lote sin errores retorna reporte vacío
    // =========================================================================

    #[Given('que existe un lote ETL con todos los registros en estado migrado')]
    public function queExisteUnLoteEtlConTodosLosRegistrosEnEstadoMigrado(): void
    {
        $loteId = $this->sembrarLoteSinErrores();

        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $registros = $repo->buscarPorLote($loteId);
        Assert::assertNotEmpty($registros, 'El lote debe contener al menos un registro');

        foreach ($registros as $registro) {
            Assert::assertTrue(
                $registro->estado()->equals(EstadoRegistroEtl::Migrado),
                "Se esperaba que todos los registros estuvieran migrados, pero {$registro->id()} está en estado {$registro->estado()->value}"
            );
        }
    }

    #[Then('el reporte retorna una lista vacía sin error')]
    public function elReporteRetornaUnaListaVaciaSinError(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'Se esperaba reporte vacío sin error, pero el handler lanzó: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertIsArray($this->ultimaRespuesta->rechazados, 'Se esperaba un array de registros rechazados');
        Assert::assertEmpty($this->ultimaRespuesta->rechazados, 'Se esperaba lista vacía para lote sin errores');
    }
}
