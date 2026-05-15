<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\ValidacionYCalidadDatos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EjecutarEtl\EjecutarEtlHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EjecutarEtl\EjecutarEtlInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RegistroEtl;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RegistroEtlRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRegistroEtl;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class MigracionEtlHistoricosContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private EjecutarEtlHandler $etlHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?string $loteId = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->etlHandler = $this->make(EjecutarEtlHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarLoteValido(): string
    {
        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $loteId = (string) $repo->nextLoteIdentity();

        $registro = RegistroEtl::crear(
            id: $repo->nextIdentity(),
            loteId: $loteId,
            nombreCientifico: 'Morpho peleides',
            fechaColecta: '2020-03-15',
            localidad: 'Napo, Ecuador',
            colector: 'Juan Pérez',
            datosOriginales: ['fuente' => 'base_historica_2010'],
        );
        $repo->guardar($registro);
        $this->loteId = $loteId;

        return $loteId;
    }

    private function sembrarLoteConCampoInvalido(string $campo): string
    {
        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $loteId = (string) $repo->nextLoteIdentity();

        $args = [
            'id' => $repo->nextIdentity(),
            'loteId' => $loteId,
            'nombreCientifico' => 'Morpho peleides',
            'fechaColecta' => '2020-03-15',
            'localidad' => 'Napo, Ecuador',
            'colector' => 'Juan Pérez',
            'datosOriginales' => ['fuente' => 'base_historica_2010'],
        ];

        $args[$campo] = '';

        $registro = RegistroEtl::crear(
            id: $args['id'],
            loteId: $args['loteId'],
            nombreCientifico: $args['nombreCientifico'],
            fechaColecta: $args['fechaColecta'],
            localidad: $args['localidad'],
            colector: $args['colector'],
            datosOriginales: $args['datosOriginales'],
        );
        $repo->guardar($registro);
        $this->loteId = $loteId;

        return $loteId;
    }

    // =========================================================================
    // ESCENARIO: Migrar registros históricos válidos exitosamente
    // =========================================================================

    #[Given('que existe un lote de registros históricos con datos válidos')]
    public function queExisteUnLoteDeRegistrosHistoricosConDatosValidos(): void
    {
        $loteId = $this->sembrarLoteValido();

        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $registros = $repo->buscarPorLote($loteId);
        Assert::assertNotEmpty($registros, 'El lote válido debe contener al menos un registro');
    }

    #[When('se ejecuta el proceso ETL sobre el lote')]
    public function seEjecutaElProcesoEtlSobreElLote(): void
    {
        Assert::assertNotNull($this->loteId, 'Se esperaba un loteId del step Dado anterior');

        try {
            $this->ultimaRespuesta = $this->etlHandler->handle(
                new EjecutarEtlInput(loteId: $this->loteId)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('los registros son migrados y quedan en estado migrado')]
    public function losRegistrosSonMigradosYQuedanEnEstadoMigrado(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertGreaterThan(0, $this->ultimaRespuesta->migrados, 'Se esperaba al menos un registro migrado');

        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $registros = $repo->buscarPorLote($this->loteId);
        foreach ($registros as $registro) {
            Assert::assertTrue(
                $registro->estado()->equals(EstadoRegistroEtl::Migrado),
                "El registro {$registro->id()} debe estar en estado migrado, se encontró: {$registro->estado()->value}"
            );
        }
    }

    // =========================================================================
    // ESQUEMA: Registros con campo inválido son rechazados durante el ETL
    // =========================================================================

    #[Given('que existe un lote con un registro que tiene el campo :campo inválido')]
    public function queExisteUnLoteConUnRegistroQuetieneElCampoInvalido(string $campo): void
    {
        $loteId = $this->sembrarLoteConCampoInvalido($campo);

        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $registros = $repo->buscarPorLote($loteId);
        Assert::assertNotEmpty($registros, 'El lote con campo inválido debe contener al menos un registro');
    }

    #[Then('el registro es rechazado con causa de error que menciona :campo')]
    public function elRegistroEsRechazadoConCausaDeErrorQueMenciona(string $campo): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertGreaterThan(0, $this->ultimaRespuesta->rechazados, 'Se esperaba al menos un registro rechazado');

        $repo = $this->make(RegistroEtlRepositoryInterface::class);
        $registros = $repo->buscarPorLote($this->loteId);
        $rechazado = null;
        foreach ($registros as $registro) {
            if ($registro->estado()->equals(EstadoRegistroEtl::Rechazado)) {
                $rechazado = $registro;
                break;
            }
        }

        Assert::assertNotNull($rechazado, 'No se encontró ningún registro en estado rechazado en el lote');
        Assert::assertStringContainsStringIgnoringCase(
            str_replace('_', ' ', $campo),
            $rechazado->causaError(),
            "La causa de error del registro rechazado debe mencionar el campo '{$campo}'"
        );
    }
}
