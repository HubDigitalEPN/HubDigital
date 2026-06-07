<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Mapa;

use Illuminate\View\View;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarComposicionGabinete\ConsultarComposicionGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarComposicionGabinete\ConsultarComposicionGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja\ConsultarContenidoCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja\ConsultarContenidoCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray\ConsultarContenidoUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray\ConsultarContenidoUnitTrayInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete\ConsultarOcupacionGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete\ConsultarOcupacionGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables\ListarEspecimenesAsignablesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables\ListarEspecimenesAsignablesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen\LocalizarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimen\LocalizarEspecimenInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Mapa interactivo y reutilizable de la colección: recorre la jerarquía física
 * Gabinete → Ranura → Caja → Unit Tray → Espécimen con carga perezosa por nivel
 * (nunca se carga el catálogo completo en memoria) y una búsqueda que navega y
 * resalta el espécimen localizado.
 *
 * Es presentación pura, sin control de acceso propio: la página que lo monta
 * decide quién entra. El prop $modo es la costura para que el portal público del
 * visitante reutilice el componente con sus propias reglas de visibilidad.
 */
final class MapaInteractivo extends Component
{
    use TraduceErroresPersistencia;

    public string $modo = 'curador';

    /**
     * Vista general: gabinetes con la ocupación de sus ranuras a nivel caja (ligera).
     * Los especímenes nunca se cargan aquí; solo al abrir un unit tray.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $gabinetes = [];

    /** @var ?array{id: string, codigo: string, nombre: string} */
    public ?array $gabineteSeleccionado = null;

    /** @var array{subfamilias: string[], generos: string[]} */
    public array $composicion = ['subfamilias' => [], 'generos' => []];

    /** @var array<int, array<string, mixed>> */
    public array $ranuras = [];

    /** @var ?array{id: string, codigo: string} */
    public ?array $cajaSeleccionada = null;

    /** @var array<int, array<string, mixed>> */
    public array $unitTrays = [];

    /** @var ?array{id: string, numero: int} */
    public ?array $unitTraySeleccionado = null;

    /** @var array<int, array<string, mixed>> */
    public array $especimenes = [];

    public string $busquedaEspecimen = '';

    /** @var array<int, array<string, mixed>> */
    public array $sugerencias = [];

    public ?string $especimenResaltadoId = null;

    public ?string $mensajeBusqueda = null;

    public ?string $errorMessage = null;

    public function mount(string $modo = 'curador'): void
    {
        $this->modo = $modo;

        $this->cargarProtegido(function (): void {
            $ocupacionHandler = app(ConsultarOcupacionGabineteHandler::class);

            $this->gabinetes = array_map(function ($g) use ($ocupacionHandler) {
                $ocupacion = $ocupacionHandler->handle(new ConsultarOcupacionGabineteInput($g->id));

                return [
                    'id' => $g->id,
                    'codigo' => $g->codigo,
                    'nombre' => $g->nombre,
                    'totalRanuras' => $g->totalRanuras,
                    'ranuras' => $this->mapearRanuras($ocupacion->items),
                ];
            }, app(ListarGabineteHandler::class)->handle()->items);
        });
    }

    public function abrirGabinete(string $gabineteId): void
    {
        $this->volverAGeneral();

        $gabinete = $this->buscarGabinete($gabineteId);
        if ($gabinete === null) {
            return;
        }

        // Las ranuras ya están en memoria desde la vista general; solo se resuelve la
        // composición (subfamilias/géneros) del gabinete para su encabezado.
        $this->cargarProtegido(function () use ($gabinete, $gabineteId): void {
            $composicion = app(ConsultarComposicionGabineteHandler::class)
                ->handle(new ConsultarComposicionGabineteInput($gabineteId));

            $this->ranuras = $gabinete['ranuras'];
            $this->composicion = [
                'subfamilias' => $composicion->subfamilias,
                'generos' => $composicion->generos,
            ];
            $this->gabineteSeleccionado = [
                'id' => $gabinete['id'],
                'codigo' => $gabinete['codigo'],
                'nombre' => $gabinete['nombre'],
            ];
        });
    }

    /**
     * @param  array<int, object>  $items
     * @return array<int, array<string, mixed>>
     */
    private function mapearRanuras(array $items): array
    {
        return array_map(fn ($r) => [
            'ranuraId' => $r->ranuraId,
            'numeroRanura' => $r->numeroRanura,
            'ocupada' => $r->ocupada,
            'cajaId' => $r->cajaId,
            'codigoCaja' => $r->codigoCaja,
            'estado' => $r->estado,
            'esEspecial' => $r->esEspecial,
            'clasificacion' => $r->clasificacion,
        ], $items);
    }

    public function abrirCaja(string $cajaId, string $codigoCaja): void
    {
        $this->cerrarUnitTray();
        $this->especimenResaltadoId = null;

        $this->cargarProtegido(function () use ($cajaId, $codigoCaja): void {
            $contenido = app(ConsultarContenidoCajaHandler::class)
                ->handle(new ConsultarContenidoCajaInput($cajaId));

            $this->unitTrays = array_map(fn ($t) => [
                'unitTrayId' => $t->unitTrayId,
                'numero' => $t->numero,
                'clasificacionDominante' => $t->clasificacionDominante,
            ], $contenido->items);

            $this->cajaSeleccionada = ['id' => $cajaId, 'codigo' => $codigoCaja];
        });
    }

    public function abrirUnitTray(string $unitTrayId, int $numero): void
    {
        $this->cargarProtegido(function () use ($unitTrayId, $numero): void {
            $contenido = app(ConsultarContenidoUnitTrayHandler::class)
                ->handle(new ConsultarContenidoUnitTrayInput($unitTrayId));

            $this->especimenes = array_map(fn ($e) => [
                'especimenId' => $e->especimenId,
                'codigoCatalogo' => $e->codigoCatalogo,
                'nombreCientifico' => $e->nombreCientifico,
            ], $contenido->items);

            $this->unitTraySeleccionado = ['id' => $unitTrayId, 'numero' => $numero];
        });
    }

    public function volverAGeneral(): void
    {
        $this->gabineteSeleccionado = null;
        $this->composicion = ['subfamilias' => [], 'generos' => []];
        $this->ranuras = [];
        $this->cerrarCaja();
    }

    public function cerrarCaja(): void
    {
        $this->cajaSeleccionada = null;
        $this->unitTrays = [];
        $this->cerrarUnitTray();
    }

    public function cerrarUnitTray(): void
    {
        $this->unitTraySeleccionado = null;
        $this->especimenes = [];
    }

    public function updatedBusquedaEspecimen(): void
    {
        $this->mensajeBusqueda = null;
        $termino = trim($this->busquedaEspecimen);

        if ($termino === '') {
            $this->sugerencias = [];

            return;
        }

        $this->cargarProtegido(function () use ($termino): void {
            $this->sugerencias = app(ListarEspecimenesAsignablesHandler::class)
                ->handle(new ListarEspecimenesAsignablesInput(busqueda: $termino, limite: 20))
                ->items;
        });
    }

    public function localizar(string $especimenId): void
    {
        $this->sugerencias = [];
        $this->busquedaEspecimen = '';
        $this->mensajeBusqueda = null;

        $this->cargarProtegido(function () use ($especimenId): void {
            $ruta = app(LocalizarEspecimenHandler::class)
                ->handle(new LocalizarEspecimenInput($especimenId));

            if (! $ruta->ubicado || $ruta->gabineteId === null) {
                $this->mensajeBusqueda = $ruta->encontrado
                    ? 'El espécimen aún no tiene una ubicación física asignada en un gabinete.'
                    : 'No se encontró el espécimen indicado.';

                return;
            }

            $this->abrirGabinete($ruta->gabineteId);

            if ($ruta->cajaId !== null && $ruta->codigoCaja !== null) {
                $this->abrirCaja($ruta->cajaId, $ruta->codigoCaja);
            }
            if ($ruta->unitTrayId !== null && $ruta->numeroUnitTray !== null) {
                $this->abrirUnitTray($ruta->unitTrayId, $ruta->numeroUnitTray);
            }

            $this->especimenResaltadoId = $ruta->especimenId;
        });
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::seguimiento-fisico.mapa.interactivo');
    }

    /** @return ?array<string, mixed> */
    private function buscarGabinete(string $gabineteId): ?array
    {
        foreach ($this->gabinetes as $g) {
            if ($g['id'] === $gabineteId) {
                return $g;
            }
        }

        return null;
    }
}
