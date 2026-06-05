<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra\ConfirmarMuestraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra\ConfirmarMuestraInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision\MarcarMuestraParaRevisionHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision\MarcarMuestraParaRevisionInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarMuestraColecta\RegistrarMuestraColectaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarMuestraColecta\RegistrarMuestraColectaInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;
use PHPUnit\Framework\Assert;

final class RegistroMuestrasColectaContext extends BaseContext
{
    private RegistrarMuestraColectaHandler $registrarHandler;

    private ConfirmarMuestraHandler $confirmarHandler;

    private MarcarMuestraParaRevisionHandler $marcarHandler;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private ?MuestraColecta $muestraExistente = null;

    public function __construct()
    {
        // Sólo bindeamos MuestraColectaRepositoryInterface. NO rebindemos
        // LocalidadRepositoryInterface porque otros contexts (Localidades) ya
        // lo bindean en el mismo escenario y la última instancia gana,
        // dejando a sus seeds desincronizados con los handlers.
        // Los escenarios de muestras no validan localidades existentes —
        // localidad_id queda null o se pasa un UUID inexistente que debe fallar.
        $muestraRepo = new InMemoryMuestraColectaRepository;
        self::$app->instance(MuestraColectaRepositoryInterface::class, $muestraRepo);

        $this->registrarHandler = $this->make(RegistrarMuestraColectaHandler::class);
        $this->confirmarHandler = $this->make(ConfirmarMuestraHandler::class);
        $this->marcarHandler = $this->make(MarcarMuestraParaRevisionHandler::class);
    }

    // =========================================================================
    // ESCENARIO: Registrar una muestra mínima con motivo
    // =========================================================================

    #[Given('que el curador tiene los datos de una muestra de colecta tentativa')]
    public function queElCuradorTieneLosDatosDeUnaMuestraDeColectaTentativa(): void
    {
        Assert::assertTrue(true, 'Datos preparados en línea en el When.');
    }

    #[When('el curador registra la muestra')]
    public function elCuradorRegistraLaMuestra(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(new RegistrarMuestraColectaInput(
                codigoMuestra: 'BT2F3',
                fechaVerbatim: '21-Jul-01',
                colector: 'Juan Pérez',
                motivoRevision: 'agrupación por oldCode sin confirmar',
            ));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la muestra queda registrada en estado pendiente con su motivo')]
    public function laMuestraQuedaRegistradaEnEstadoPendienteConSuMotivo(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción al registrar.');
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame('pendiente', $this->ultimaRespuesta->estadoRevision);
        Assert::assertSame('agrupación por oldCode sin confirmar', $this->ultimaRespuesta->motivoRevision);
    }

    // =========================================================================
    // ESCENARIO: Confirmar una muestra pendiente
    // =========================================================================

    #[Given('que existe una muestra de colecta en estado pendiente')]
    public function queExisteUnaMuestraDeColectaEnEstadoPendiente(): void
    {
        $repo = $this->make(MuestraColectaRepositoryInterface::class);
        $muestra = MuestraColecta::crear(
            id: $repo->nextIdentity(),
            codigoMuestra: 'BT2F3',
            motivoRevision: 'agrupación por oldCode sin confirmar',
        );
        $repo->guardar($muestra);
        $this->muestraExistente = $muestra;
    }

    #[When('el curador confirma la muestra')]
    public function elCuradorConfirmaLaMuestra(): void
    {
        Assert::assertNotNull($this->muestraExistente);
        try {
            $this->ultimaRespuesta = $this->confirmarHandler->handle(new ConfirmarMuestraInput(
                muestraId: (string) $this->muestraExistente->id(),
            ));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la muestra queda en estado confirmada sin motivo de revisión')]
    public function laMuestraQuedaEnEstadoConfirmadaSinMotivoDeRevision(): void
    {
        Assert::assertNull($this->excepcionCapturada);
        Assert::assertSame('confirmada', $this->ultimaRespuesta->estadoRevision);

        $repo = $this->make(MuestraColectaRepositoryInterface::class);
        $persistida = $repo->buscarPorId($this->muestraExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue($persistida->estadoRevision()->equals(EstadoRevision::Confirmada));
        Assert::assertNull($persistida->motivoRevision());
    }

    // =========================================================================
    // ESCENARIO: Marcar una muestra confirmada para revisión
    // =========================================================================

    #[Given('que existe una muestra de colecta confirmada')]
    public function queExisteUnaMuestraDeColectaConfirmada(): void
    {
        $repo = $this->make(MuestraColectaRepositoryInterface::class);
        $muestra = MuestraColecta::crear(id: $repo->nextIdentity(), codigoMuestra: 'BT2F3');
        $muestra->confirmar();
        $repo->guardar($muestra);
        $this->muestraExistente = $muestra;
    }

    #[When('el curador marca la muestra para revisión con un motivo')]
    public function elCuradorMarcaLaMuestraParaRevisionConUnMotivo(): void
    {
        Assert::assertNotNull($this->muestraExistente);
        try {
            $this->ultimaRespuesta = $this->marcarHandler->handle(new MarcarMuestraParaRevisionInput(
                muestraId: (string) $this->muestraExistente->id(),
                motivo: 'discrepancia detectada en localidad enlazada',
            ));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la muestra vuelve a estado pendiente con el motivo registrado')]
    public function laMuestraVuelveAEstadoPendienteConElMotivoRegistrado(): void
    {
        Assert::assertNull($this->excepcionCapturada);
        Assert::assertSame('pendiente', $this->ultimaRespuesta->estadoRevision);
        Assert::assertSame('discrepancia detectada en localidad enlazada', $this->ultimaRespuesta->motivoRevision);
    }
}
