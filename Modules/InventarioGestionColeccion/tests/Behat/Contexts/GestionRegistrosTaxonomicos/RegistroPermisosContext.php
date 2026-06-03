<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisos\ListarPermisosHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso\RegistrarPermisoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso\RegistrarPermisoInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryPermisoRepository;
use PHPUnit\Framework\Assert;

final class RegistroPermisosContext extends BaseContext
{
    private RegistrarPermisoHandler $registrarHandler;

    private ListarPermisosHandler $listarHandler;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    /** @var array<string, mixed> */
    private array $datosPermiso = [
        'tipo' => 'research',
        'numero' => 'INV-2026-001',
        'responsable' => 'Dr. Juan Pérez',
        'detalles' => 'Permiso de investigación entomológica.',
    ];

    public function __construct()
    {
        $repo = new InMemoryPermisoRepository;
        self::$app->instance(PermisoRepositoryInterface::class, $repo);

        $this->registrarHandler = $this->make(RegistrarPermisoHandler::class);
        $this->listarHandler = $this->make(ListarPermisosHandler::class);
    }

    // =========================================================================
    // ESCENARIO: Registrar permiso de investigación
    // =========================================================================

    #[Given('que el curador tiene los datos de un permiso de investigación')]
    public function queElCuradorTieneLosDatosDeUnPermisoDeInvestigacion(): void
    {
        Assert::assertSame('research', $this->datosPermiso['tipo']);
    }

    #[When('el curador registra el permiso')]
    public function elCuradorRegistraElPermiso(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(new RegistrarPermisoInput(
                tipo: $this->datosPermiso['tipo'],
                numero: $this->datosPermiso['numero'] ?? null,
                responsable: $this->datosPermiso['responsable'] ?? null,
                detalles: $this->datosPermiso['detalles'] ?? null,
            ));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el permiso queda registrado en el sistema')]
    public function elPermisoQuedaRegistradoEnElSistema(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción al registrar.');
        Assert::assertNotNull($this->ultimaRespuesta);

        $repo = $this->make(PermisoRepositoryInterface::class);
        $persistido = $repo->buscarPorId($this->ultimaRespuesta->id);
        Assert::assertNotNull($persistido);
        Assert::assertSame('research', $persistido->tipo()->value);
    }

    // =========================================================================
    // ESCENARIO: Permiso con tipo inválido
    // =========================================================================

    #[Given('que el curador tiene un permiso con tipo desconocido')]
    public function queElCuradorTieneUnPermisoConTipoDesconocido(): void
    {
        $this->datosPermiso['tipo'] = 'almacenamiento';
    }

    #[Then('el sistema rechaza el registro indicando tipo inválido')]
    public function elSistemaRechazaElRegistroIndicandoTipoInvalido(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba un error de validación de tipo pero el handler completó sin excepción'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'tipo',
            $this->excepcionCapturada->getMessage(),
            'El mensaje debe mencionar el tipo inválido'
        );
    }

    // =========================================================================
    // ESCENARIO: Listar permisos
    // =========================================================================

    #[Given('que existen varios permisos registrados de distintos tipos')]
    public function queExistenVariosPermisosRegistradosDeDistintosTipos(): void
    {
        $repo = $this->make(PermisoRepositoryInterface::class);
        $repo->guardar(Permiso::crear($repo->nextIdentity(), 'research', 'INV-2026-001', 'Dra. María Cárdenas'));
        $repo->guardar(Permiso::crear($repo->nextIdentity(), 'transport', 'TRA-2026-001', 'Dr. Juan Pérez'));
        $repo->guardar(Permiso::crear($repo->nextIdentity(), 'export_import', 'EXP-2026-001', 'Dra. Ana Lopez'));
    }

    #[When('el curador lista los permisos')]
    public function elCuradorListaLosPermisos(): void
    {
        try {
            $this->ultimaRespuesta = $this->listarHandler->handle();
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se muestran todos los permisos con su tipo y responsable')]
    public function seMuestranTodosLosPermisosConSuTipoYResponsable(): void
    {
        Assert::assertNull($this->excepcionCapturada);
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(3, $this->ultimaRespuesta->total);

        $tipos = array_map(fn ($i) => $i->tipo, $this->ultimaRespuesta->items);
        Assert::assertContains('research', $tipos);
        Assert::assertContains('transport', $tipos);
        Assert::assertContains('export_import', $tipos);
    }
}
