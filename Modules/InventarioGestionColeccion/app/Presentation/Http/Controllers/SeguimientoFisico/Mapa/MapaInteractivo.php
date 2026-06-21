<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Mapa;

use Illuminate\View\View;
use Livewire\Attributes\Url;
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
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimenParaVisitante\LocalizarEspecimenParaVisitanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\LocalizarEspecimenParaVisitante\LocalizarEspecimenParaVisitanteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerOrdenFamiliasColeccion\ObtenerOrdenFamiliasColeccionHandler;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Mapa interactivo y reutilizable de la colección: recorre la jerarquía física
 * Gabinete → Ranura → Caja → Unit Tray → Espécimen con carga perezosa por nivel
 * (nunca se carga el catálogo completo en memoria) y una búsqueda que navega y
 * resalta el espécimen localizado.
 *
 * La ruta de zoom (gabinete → caja → unit tray) se refleja en la URL con history,
 * de modo que el botón «atrás» del navegador retrocede un nivel de zoom en lugar de
 * abandonar el mapa, y un enlace directo a un nivel abre el mapa ya posicionado. La
 * vista mostrada se deriva siempre de esos identificadores en cada render, por lo que
 * navegar por clic o por historial produce exactamente el mismo estado.
 *
 * Es presentación pura, sin control de acceso propio: la página que lo monta
 * decide quién entra. El prop $modo es la costura para que el portal público del
 * visitante reutilice el componente con sus propias reglas de visibilidad.
 */
final class MapaInteractivo extends Component
{
    use TraduceErroresPersistencia;

    public string $modo = 'curador';

    /** Identificador del gabinete con zoom abierto; vacío = vista general. */
    #[Url(as: 'gab', history: true)]
    public string $gabineteAbierto = '';

    /** Identificador de la caja con zoom abierto dentro del gabinete; vacío = nivel gabinete. */
    #[Url(as: 'caja', history: true)]
    public string $cajaAbierta = '';

    /** Identificador del unit tray con zoom abierto dentro de la caja; vacío = nivel caja. */
    #[Url(as: 'tray', history: true)]
    public string $trayAbierto = '';

    /**
     * Vista compacta de la general: minimiza las cajas para mostrar muchos gabinetes a la vez,
     * listando solo familias (con detalle de subfamilias/géneros desplegable). Reflejada en la URL.
     */
    #[Url(as: 'compacto', history: true)]
    public bool $vistaCompacta = false;

    /**
     * Vista general: gabinetes con la ocupación de sus ranuras a nivel caja (ligera).
     * Los especímenes nunca se cargan aquí; solo al abrir un unit tray.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $gabinetes = [];

    /**
     * Familias presentes en la colección en su orden canónico (secuencia del curador, luego
     * alfabético). Define el índice de color de cada familia para que el coloreado por familia
     * sea estable y consistente entre gabinetes (misma familia → mismo color en todo el mapa).
     *
     * @var string[]
     */
    public array $ordenFamilias = [];

    /** @var ?array{id: string, codigo: string, nombre: string} */
    public ?array $gabineteSeleccionado = null;

    /** @var array{subfamilias: string[], generos: string[]} */
    public array $composicion = ['subfamilias' => [], 'generos' => []];

    /** @var array<int, array<string, mixed>> */
    public array $ranuras = [];

    /** @var ?array{id: string, codigo: string, numeroRanura: int} */
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

    /**
     * Arma la vista general del mapa: lista los gabinetes con la ocupación de sus
     * ranuras a nivel caja (ligera). Recibe el modo de uso ('curador' por defecto), la
     * costura con la que el portal público puede reutilizar el componente. Los
     * especímenes nunca se cargan aquí, solo al abrir un unit tray. El nivel de zoom
     * mostrado lo deriva render() a partir de los identificadores de la URL.
     */
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

            // Orden canónico de familias presentes: fija el índice de color por familia para que
            // el coloreado sea estable y global. Acotado por el número de cajas, no de especímenes.
            $orden = app(ObtenerOrdenFamiliasColeccionHandler::class)->handle();
            $this->ordenFamilias = array_values(array_map(
                fn ($f) => $f->familia,
                array_filter($orden->familias, fn ($f) => $f->presente),
            ));
        });
    }

    /** Entra al detalle de un gabinete fijando la ruta de zoom en la URL. */
    public function abrirGabinete(string $gabineteId): void
    {
        $this->gabineteAbierto = $gabineteId;
        $this->cajaAbierta = '';
        $this->trayAbierto = '';
        $this->especimenResaltadoId = null;
    }

    /**
     * Baja al nivel de caja fijando la ruta de zoom en la URL. El código de la caja se
     * reaprovecha del clic, pero la vista se reconstruye siempre desde el identificador.
     */
    public function abrirCaja(string $cajaId, string $codigoCaja = ''): void
    {
        $this->cajaAbierta = $cajaId;
        $this->trayAbierto = '';
        $this->especimenResaltadoId = null;
    }

    /**
     * Salta directo al nivel de caja desde la vista general (sin pasar por el detalle del
     * gabinete): fija a la vez el gabinete y la caja en la ruta de zoom. La vista se reconstruye
     * desde esos identificadores igual que con cualquier otra navegación.
     */
    public function abrirCajaDirecto(string $gabineteId, string $cajaId): void
    {
        $this->gabineteAbierto = $gabineteId;
        $this->cajaAbierta = $cajaId;
        $this->trayAbierto = '';
        $this->especimenResaltadoId = null;
    }

    /** Alterna la vista compacta (minimizada) de la general para ver muchos gabinetes a la vez. */
    public function alternarVistaCompacta(): void
    {
        $this->vistaCompacta = ! $this->vistaCompacta;
    }

    /** Baja al último nivel (especímenes del unit tray) fijando la ruta de zoom en la URL. */
    public function abrirUnitTray(string $unitTrayId, int $numero = 0): void
    {
        $this->trayAbierto = $unitTrayId;
    }

    /** Vuelve a la vista general del mapa, limpiando toda la ruta de zoom. */
    public function volverAGeneral(): void
    {
        $this->gabineteAbierto = '';
        $this->cajaAbierta = '';
        $this->trayAbierto = '';
        $this->especimenResaltadoId = null;
    }

    /** Cierra el detalle de caja (y el unit tray que tuviera abierto), volviendo al nivel de gabinete. */
    public function cerrarCaja(): void
    {
        $this->cajaAbierta = '';
        $this->trayAbierto = '';
    }

    /** Cierra el detalle de unit tray, volviendo al nivel de caja. */
    public function cerrarUnitTray(): void
    {
        $this->trayAbierto = '';
    }

    /**
     * Actualiza las sugerencias de búsqueda mientras el usuario escribe: con texto vacío
     * las limpia; si no, consulta de forma acotada (hasta 20) los especímenes que
     * coinciden, sin tocar la navegación del mapa.
     */
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

    /**
     * Localiza un espécimen y navega automáticamente hasta él: resuelve su ruta física
     * (gabinete → caja → unit tray) y fija la ruta de zoom en la URL para que el render
     * abra cada nivel y lo deje resaltado. Si no se encuentra o aún no tiene ubicación
     * física asignada, muestra el mensaje correspondiente sin navegar.
     */
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

            $this->gabineteAbierto = $ruta->gabineteId;
            $this->cajaAbierta = $ruta->cajaId ?? '';
            $this->trayAbierto = $ruta->unitTrayId ?? '';
            $this->especimenResaltadoId = $ruta->especimenId;
        });
    }

    /**
     * Localiza por nombre científico para el visitante y navega hasta el espécimen.
     * Aplica la guía pública: la ubicación se entrega siempre que el espécimen esté
     * colocado en un unit tray; si no, informa que no está disponible públicamente sin
     * navegar. No expone identificadores: el visitante busca solo por nombre.
     */
    public function localizarPorNombre(string $nombre): void
    {
        $this->sugerencias = [];
        $this->mensajeBusqueda = null;

        $this->cargarProtegido(function () use ($nombre): void {
            $ruta = app(LocalizarEspecimenParaVisitanteHandler::class)
                ->handle(new LocalizarEspecimenParaVisitanteInput($nombre));

            if (! $ruta->disponiblePublicamente || $ruta->gabineteId === null) {
                $this->mensajeBusqueda = $ruta->encontrado
                    ? 'La ubicación de ese espécimen no está disponible públicamente.'
                    : 'No se encontró ningún espécimen con ese nombre científico.';

                return;
            }

            $this->gabineteAbierto = $ruta->gabineteId;
            $this->cajaAbierta = $ruta->cajaId ?? '';
            $this->trayAbierto = $ruta->unitTrayId ?? '';
            $this->especimenResaltadoId = $ruta->especimenId;
        });
    }

    public function render(): View
    {
        $this->sincronizarVistaConRuta();

        return view('inventariogestioncoleccion::seguimiento-fisico.mapa.interactivo');
    }

    /**
     * Reconstruye la vista (gabinete → caja → unit tray) a partir de los identificadores
     * de zoom de la URL. Es la única fuente de verdad de la navegación: tanto un clic como
     * el botón «atrás» del navegador solo cambian esos identificadores, y aquí se deriva el
     * mismo estado de forma perezosa por nivel.
     */
    private function sincronizarVistaConRuta(): void
    {
        $this->gabineteSeleccionado = null;
        $this->composicion = ['subfamilias' => [], 'generos' => []];
        $this->ranuras = [];
        $this->cajaSeleccionada = null;
        $this->unitTrays = [];
        $this->unitTraySeleccionado = null;
        $this->especimenes = [];

        if ($this->gabineteAbierto === '') {
            return;
        }

        $gabinete = $this->buscarGabinete($this->gabineteAbierto);
        if ($gabinete === null) {
            // La URL apunta a un gabinete que no está en la vista general: se ignora.
            return;
        }

        $this->cargarProtegido(function () use ($gabinete): void {
            $composicion = app(ConsultarComposicionGabineteHandler::class)
                ->handle(new ConsultarComposicionGabineteInput($this->gabineteAbierto));

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

            if ($this->cajaAbierta === '') {
                return;
            }

            $contenido = app(ConsultarContenidoCajaHandler::class)
                ->handle(new ConsultarContenidoCajaInput($this->cajaAbierta));

            $this->unitTrays = array_map(fn ($t) => [
                'unitTrayId' => $t->unitTrayId,
                'numero' => $t->numero,
                'clasificacionDominante' => $t->clasificacionDominante,
            ], $contenido->items);

            $this->cajaSeleccionada = [
                'id' => $this->cajaAbierta,
                'codigo' => $this->codigoDeCajaEnRanuras($this->cajaAbierta),
                'numeroRanura' => $this->numeroRanuraDeCaja($this->cajaAbierta),
            ];

            if ($this->trayAbierto === '') {
                return;
            }

            $contenidoTray = app(ConsultarContenidoUnitTrayHandler::class)
                ->handle(new ConsultarContenidoUnitTrayInput($this->trayAbierto));

            $this->especimenes = array_map(fn ($e) => [
                'especimenId' => $e->especimenId,
                'codigoCatalogo' => $e->codigoCatalogo,
                'nombreCientifico' => $e->nombreCientifico,
                'genero' => $e->genero,
                'especie' => $e->especie,
                'individualCount' => $e->individualCount,
                'provincia' => $e->provincia,
                'localidad' => $e->localidad,
            ], $contenidoTray->items);

            $this->unitTraySeleccionado = [
                'id' => $this->trayAbierto,
                'numero' => $this->numeroDeTray($this->trayAbierto),
            ];
        });
    }

    /**
     * Mapea la ocupación de las ranuras de un gabinete a una estructura plana para la
     * vista (número, si está ocupada, caja que ocupa, estado y clasificación).
     *
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

    /**
     * Busca un gabinete ya cargado en la vista general por su id, para reaprovechar sus
     * ranuras sin volver a consultarlas.
     *
     * @return ?array<string, mixed>
     */
    private function buscarGabinete(string $gabineteId): ?array
    {
        foreach ($this->gabinetes as $g) {
            if ($g['id'] === $gabineteId) {
                return $g;
            }
        }

        return null;
    }

    /** Resuelve el código legible de una caja a partir de las ranuras ya cargadas del gabinete. */
    private function codigoDeCajaEnRanuras(string $cajaId): string
    {
        foreach ($this->ranuras as $r) {
            if (($r['cajaId'] ?? null) === $cajaId) {
                return (string) ($r['codigoCaja'] ?? '');
            }
        }

        return '';
    }

    /** Resuelve el número de ranura física que ocupa una caja a partir de las ranuras ya cargadas del gabinete. */
    private function numeroRanuraDeCaja(string $cajaId): int
    {
        foreach ($this->ranuras as $r) {
            if (($r['cajaId'] ?? null) === $cajaId) {
                return (int) ($r['numeroRanura'] ?? 0);
            }
        }

        return 0;
    }

    /** Resuelve el número de un unit tray a partir de los trays ya cargados de la caja. */
    private function numeroDeTray(string $unitTrayId): int
    {
        foreach ($this->unitTrays as $t) {
            if ($t['unitTrayId'] === $unitTrayId) {
                return (int) $t['numero'];
            }
        }

        return 0;
    }
}
