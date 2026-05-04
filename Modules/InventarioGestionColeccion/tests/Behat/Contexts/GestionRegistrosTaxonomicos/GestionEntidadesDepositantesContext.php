<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante\ActualizarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante\ActualizarEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class GestionEntidadesDepositantesContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarEntidadDepositanteHandler $registrarHandler;

    private ActualizarEntidadDepositanteHandler $actualizarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?EntidadDepositante $entidadExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private array $datosEntidadValidos = [
        'nombre' => 'Universidad Central del Ecuador',
        'tipo' => 'institucion',
        'contacto' => 'contacto@uce.edu.ec',
    ];

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->registrarHandler = $this->make(RegistrarEntidadDepositanteHandler::class);
        $this->actualizarHandler = $this->make(ActualizarEntidadDepositanteHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarEntidadBase(): EntidadDepositante
    {
        $repo = $this->make(EntidadDepositanteRepositoryInterface::class);
        $entidad = EntidadDepositante::crear(
            id: $repo->nextIdentity(),
            nombre: $this->datosEntidadValidos['nombre'],
            tipo: $this->datosEntidadValidos['tipo'],
            contacto: $this->datosEntidadValidos['contacto'],
        );

        $repo->guardar($entidad);
        $this->entidadExistente = $entidad;

        return $entidad;
    }

    // =========================================================================
    // ESCENARIO: Registrar una nueva entidad depositante
    // =========================================================================

    #[Given('que el curador tiene los datos de una nueva entidad depositante')]
    public function queElCuradorTieneLosDataDeUnaNuevaEntidadDepositante(): void
    {
        Assert::assertNotEmpty($this->datosEntidadValidos['nombre'], 'nombre es requerido');
        Assert::assertNotEmpty($this->datosEntidadValidos['tipo'], 'tipo es requerido');
        Assert::assertNotEmpty($this->datosEntidadValidos['contacto'], 'contacto es requerido');
    }

    #[When('el curador registra la entidad depositante')]
    public function elCuradorRegistraLaEntidadDepositante(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarEntidadDepositanteInput(
                    nombre: $this->datosEntidadValidos['nombre'],
                    tipo: $this->datosEntidadValidos['tipo'],
                    contacto: $this->datosEntidadValidos['contacto'],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la entidad depositante queda registrada en el sistema')]
    public function laEntidadDepositanteQuedaRegistradaEnElSistema(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(EntidadDepositanteRepositoryInterface::class);
        $entidad = $repo->buscarPorId($this->ultimaRespuesta->id);
        Assert::assertNotNull($entidad, 'La entidad no fue encontrada en el repositorio tras su registro');
        Assert::assertSame($this->datosEntidadValidos['nombre'], $entidad->nombre());
    }

    // =========================================================================
    // ESCENARIO: Editar datos de una entidad depositante existente
    // =========================================================================

    #[Given('que existe una entidad depositante registrada en el sistema')]
    public function queExisteUnaEntidadDepositanteRegistradaEnElSistema(): void
    {
        $entidad = $this->sembrarEntidadBase();

        $repo = $this->make(EntidadDepositanteRepositoryInterface::class);
        $persistida = $repo->buscarPorId($entidad->id());
        Assert::assertNotNull($persistida, 'La entidad no fue encontrada en el repositorio tras guardar');
        Assert::assertNotEmpty($persistida->nombre(), 'El nombre de la entidad no debe estar vacío');
    }

    #[When('el curador actualiza los datos de la entidad depositante')]
    public function elCuradorActualizaLosDatosDelaEntidadDepositante(): void
    {
        Assert::assertNotNull($this->entidadExistente, 'Se esperaba una entidad existente del step Dado anterior');

        $nuevoContacto = 'nuevo.contacto@uce.edu.ec';
        Assert::assertNotSame(
            $nuevoContacto,
            $this->entidadExistente->contacto(),
            'El contacto nuevo debe ser distinto al original'
        );

        try {
            $this->ultimaRespuesta = $this->actualizarHandler->handle(
                new ActualizarEntidadDepositanteInput(
                    entidadId: (string) $this->entidadExistente->id(),
                    nombre: $this->entidadExistente->nombre(),
                    tipo: $this->entidadExistente->tipo()->value,
                    contacto: $nuevoContacto,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la entidad depositante refleja la información actualizada')]
    public function laEntidadDepositanteReflejaLaInformacionActualizada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame('nuevo.contacto@uce.edu.ec', $this->ultimaRespuesta->contacto);
    }

    // =========================================================================
    // ESCENARIO: No se puede registrar una entidad depositante con nombre duplicado
    // =========================================================================

    #[Given('que existe una entidad depositante con el nombre :nombre')]
    public function queExisteUnaEntidadDepositanteConElNombre(string $nombre): void
    {
        $this->datosEntidadValidos['nombre'] = $nombre;
        $entidad = $this->sembrarEntidadBase();

        $repo = $this->make(EntidadDepositanteRepositoryInterface::class);
        $persistida = $repo->buscarPorId($entidad->id());
        Assert::assertNotNull($persistida, 'La entidad base no fue encontrada en el repositorio');
        Assert::assertSame($nombre, $persistida->nombre(), 'El nombre persistido no coincide con el esperado');
    }

    #[When('el curador intenta registrar otra entidad con el mismo nombre')]
    public function elCuradorIntentaRegistrarOtraEntidadConElMismoNombre(): void
    {
        Assert::assertNotNull($this->entidadExistente, 'Se esperaba una entidad existente del step Dado anterior');

        $nombreDuplicado = $this->entidadExistente->nombre();
        Assert::assertNotEmpty($nombreDuplicado, 'El nombre a duplicar no debe estar vacío');

        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarEntidadDepositanteInput(
                    nombre: $nombreDuplicado,
                    tipo: 'persona',
                    contacto: 'otro@correo.com',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza el registro indicando nombre de entidad duplicado')]
    public function elSistemaRechazaElRegistroIndicandoNombreDeEntidadDuplicado(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el registro fallara por nombre duplicado pero el handler completó sin error'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'duplicado',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe indicar que el nombre está duplicado'
        );
    }
}
