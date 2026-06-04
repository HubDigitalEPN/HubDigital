<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades\ListarLocalidadesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades\ListarLocalidadesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad\RegistrarLocalidadHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad\RegistrarLocalidadInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;
use PHPUnit\Framework\Assert;

final class RegistroLocalidadesContext extends BaseContext
{
    private RegistrarLocalidadHandler $registrarHandler;

    private ListarLocalidadesHandler $listarHandler;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    /** @var array<string, mixed> */
    private array $datosLocalidad = [
        'nombre_canonico' => 'Estación Yasuní',
        'rango' => 'sitio',
        'country' => 'Ecuador',
    ];

    public function __construct()
    {
        $repo = new InMemoryLocalidadRepository;
        self::$app->instance(LocalidadRepositoryInterface::class, $repo);

        $this->registrarHandler = $this->make(RegistrarLocalidadHandler::class);
        $this->listarHandler = $this->make(ListarLocalidadesHandler::class);
    }

    // =========================================================================
    // ESCENARIO: El curador registra una localidad raíz
    // =========================================================================

    #[Given('que el curador tiene los datos de una nueva localidad')]
    public function queElCuradorTieneLosDatosDeUnaNuevaLocalidad(): void
    {
        Assert::assertNotEmpty($this->datosLocalidad['nombre_canonico'], 'nombre_canonico es requerido');
        Assert::assertNotEmpty($this->datosLocalidad['rango'], 'rango es requerido');
    }

    #[When('el curador registra la localidad')]
    public function elCuradorRegistraLaLocalidad(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(new RegistrarLocalidadInput(
                nombreCanonico: $this->datosLocalidad['nombre_canonico'],
                rango: $this->datosLocalidad['rango'],
                country: $this->datosLocalidad['country'] ?? null,
            ));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la localidad queda registrada en el sistema')]
    public function laLocalidadQuedaRegistradaEnElSistema(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(LocalidadRepositoryInterface::class);
        $persistida = $repo->buscarPorId($this->ultimaRespuesta->id);
        Assert::assertNotNull($persistida, 'La localidad no fue encontrada en el repositorio');
        Assert::assertSame($this->datosLocalidad['nombre_canonico'], $persistida->nombreCanonico());
    }

    // =========================================================================
    // ESCENARIO: Localidad duplicada (mismo nombre + rango)
    // =========================================================================

    #[Given('que existe una localidad registrada en el sistema')]
    public function queExisteUnaLocalidadRegistradaEnElSistema(): void
    {
        $repo = $this->make(LocalidadRepositoryInterface::class);
        $repo->guardar(Localidad::crear(
            id: $repo->nextIdentity(),
            nombreCanonico: $this->datosLocalidad['nombre_canonico'],
            rango: $this->datosLocalidad['rango'],
        ));
    }

    #[When('el curador intenta registrar la misma localidad')]
    public function elCuradorIntentaRegistrarLaMismaLocalidad(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(new RegistrarLocalidadInput(
                nombreCanonico: $this->datosLocalidad['nombre_canonico'],
                rango: $this->datosLocalidad['rango'],
            ));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza el registro indicando que ya existe')]
    public function elSistemaRechazaElRegistroIndicandoQueYaExiste(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba un error de duplicado pero el handler completó sin excepción'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'ya existe',
            $this->excepcionCapturada->getMessage(),
            'El mensaje debe indicar que la localidad ya existe'
        );
    }

    // =========================================================================
    // ESCENARIO: Rango inválido
    // =========================================================================

    #[Given('que el curador tiene datos de una localidad con rango inválido')]
    public function queElCuradorTieneDatosDeUnaLocalidadConRangoInvalido(): void
    {
        $this->datosLocalidad['rango'] = 'continente';
    }

    #[Then('el sistema rechaza el registro indicando rango inválido')]
    public function elSistemaRechazaElRegistroIndicandoRangoInvalido(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba un error de validación de rango pero el handler completó sin excepción'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'rango',
            $this->excepcionCapturada->getMessage(),
            'El mensaje debe mencionar el rango inválido'
        );
    }

    // =========================================================================
    // ESCENARIO: Listado paginado
    // =========================================================================

    #[Given('que existen varias localidades registradas en el sistema')]
    public function queExistenVariasLocalidadesRegistradasEnElSistema(): void
    {
        $repo = $this->make(LocalidadRepositoryInterface::class);
        $pais = Localidad::crear($repo->nextIdentity(), 'Ecuador', 'pais');
        $repo->guardar($pais);
        $repo->guardar(Localidad::crear(
            id: $repo->nextIdentity(),
            nombreCanonico: 'Pichincha',
            rango: 'provincia',
            padreId: (string) $pais->id(),
        ));
        $repo->guardar(Localidad::crear(
            id: $repo->nextIdentity(),
            nombreCanonico: 'Yasuní',
            rango: 'area_protegida',
            padreId: (string) $pais->id(),
        ));
    }

    #[When('el curador lista las localidades')]
    public function elCuradorListaLasLocalidades(): void
    {
        try {
            $this->ultimaRespuesta = $this->listarHandler->handle(new ListarLocalidadesInput(perPage: 50));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se muestran las localidades ordenadas por nombre con su jerarquía')]
    public function seMuestranLasLocalidadesOrdenadasPorNombreConSuJerarquia(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(3, $this->ultimaRespuesta->total, 'Debe haber 3 localidades');

        $nombres = array_map(fn ($item) => $item->nombreCanonico, $this->ultimaRespuesta->items);
        Assert::assertSame(['Ecuador', 'Pichincha', 'Yasuní'], $nombres, 'Las localidades deben venir ordenadas alfabéticamente');

        $pichincha = array_values(array_filter(
            $this->ultimaRespuesta->items,
            fn ($i) => $i->nombreCanonico === 'Pichincha'
        ))[0];
        Assert::assertSame('Ecuador', $pichincha->padreNombre, 'Pichincha debe mostrar a Ecuador como padre');
    }
}
