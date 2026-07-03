<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorNombre\BuscarLocalidadesPorNombreHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorNombre\BuscarLocalidadesPorNombreInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim\ConfirmarLocalidadCanonicaParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim\ConfirmarLocalidadCanonicaParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearLocalidadYEnlazarVerbatim\CrearLocalidadYEnlazarVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearLocalidadYEnlazarVerbatim\CrearLocalidadYEnlazarVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo\ListarEspecimenesDeGrupoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo\ListarEspecimenesDeGrupoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes\ListarLocalidadVerbatimsPendientesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes\ListarLocalidadVerbatimsPendientesInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Localidades por confirmar'])]
final class LocalidadesRevisionIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * @var list<array{
     *   verbatim: string,
     *   totalEspecimenes: int,
     *   esNombreUnico: bool,
     *   candidatos: list<array{localidadId:string, nombreCanonico:string, rango:string, puntajeSimilitud:float}>,
     *   localidadSeleccionada: string,
     *   localidadSeleccionadaNombre: string,
     * }>
     */
    public array $items = [];

    public int $total = 0;

    public int $pagina = 1;

    public int $porPagina = 20;

    public int $limiteCandidatos = 5;

    /** Grupos expandidos: idx => true. */
    public array $expandido = [];

    /** Especímenes cargados de cada grupo: idx => list<array{id,codigoCatalogo,colector,localidad}>. */
    public array $miembros = [];

    /** Ids seleccionados por grupo: idx => list<string>. */
    public array $seleccion = [];

    /** Texto del buscador "otro nombre": idx => string. */
    public array $busquedaTexto = [];

    /** Resultados del buscador: idx => list<array{localidadId,nombreCanonico,rango}>. */
    public array $busquedaResultados = [];

    /** Nombre de la localidad nueva a crear por grupo: idx => string. */
    public array $nuevaLocalidadNombre = [];

    /** Rango de la localidad nueva a crear por grupo: idx => string. */
    public array $nuevaLocalidadRango = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarLocalidadVerbatimsPendientesHandler $handler): void
    {
        $this->cargar($handler);
    }

    public function cargar(ListarLocalidadVerbatimsPendientesHandler $handler): void
    {
        try {
            $output = $handler->handle(new ListarLocalidadVerbatimsPendientesInput(
                limiteCandidatosPorVerbatim: $this->limiteCandidatos,
                pagina: $this->pagina,
                porPagina: $this->porPagina,
            ));

            // Reencaje: si la página quedó fuera de rango tras confirmar el
            // último grupo, vuelve al último válido y reconsulta (evita la lista
            // vacía con un total que dice que aún hay grupos).
            $maxPaginas = $output->totalVerbatimsDistintos === 0
                ? 1
                : (int) ceil($output->totalVerbatimsDistintos / $this->porPagina);
            if ($this->pagina > $maxPaginas) {
                $this->pagina = $maxPaginas;
                $output = $handler->handle(new ListarLocalidadVerbatimsPendientesInput(
                    limiteCandidatosPorVerbatim: $this->limiteCandidatos,
                    pagina: $this->pagina,
                    porPagina: $this->porPagina,
                ));
            }

            $this->total = $output->totalVerbatimsDistintos;
            $this->items = array_map(fn ($item) => [
                'verbatim' => $item->verbatim,
                'totalEspecimenes' => $item->totalEspecimenes,
                'esNombreUnico' => $item->esNombreUnico,
                'candidatos' => array_map(fn ($c) => [
                    'localidadId' => $c->localidadId,
                    'nombreCanonico' => $c->nombreCanonico,
                    'rango' => $c->rango,
                    'puntajeSimilitud' => $c->puntajeSimilitud,
                ], $item->candidatos),
                'localidadSeleccionada' => $item->candidatos[0]->localidadId ?? '',
                'localidadSeleccionadaNombre' => $item->candidatos[0]->nombreCanonico ?? '',
            ], $output->items);
            $this->expandido = [];
            $this->miembros = [];
            $this->seleccion = [];
            $this->busquedaTexto = [];
            $this->busquedaResultados = [];
            $this->nuevaLocalidadNombre = [];
            $this->nuevaLocalidadRango = [];
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarLocalidadVerbatimsPendientesHandler $handler): void
    {
        $maxPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
        if ($this->pagina < $maxPaginas) {
            $this->successMessage = null;
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarLocalidadVerbatimsPendientesHandler $handler): void
    {
        if ($this->pagina > 1) {
            $this->successMessage = null;
            $this->pagina--;
            $this->cargar($handler);
        }
    }

    public function seleccionarCandidato(int $idx, string $localidadId): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }

        $nombre = '';
        foreach ($this->items[$idx]['candidatos'] ?? [] as $c) {
            if ($c['localidadId'] === $localidadId) {
                $nombre = $c['nombreCanonico'];
                break;
            }
        }
        if ($nombre === '') {
            foreach ($this->busquedaResultados[$idx] ?? [] as $r) {
                if ($r['localidadId'] === $localidadId) {
                    $nombre = $r['nombreCanonico'];
                    break;
                }
            }
        }

        $this->items[$idx]['localidadSeleccionada'] = $localidadId;
        $this->items[$idx]['localidadSeleccionadaNombre'] = $nombre;
    }

    public function verEspecimenes(ListarEspecimenesDeGrupoHandler $handler, int $idx): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }

        if (! empty($this->expandido[$idx])) {
            $this->expandido[$idx] = false;
            // Al cerrar, descarta la selección parcial: con el grupo colapsado la
            // acción aplica a TODO el grupo, así que una selección retenida sería
            // engañosa.
            unset($this->seleccion[$idx], $this->miembros[$idx]);

            return;
        }

        try {
            $output = $handler->handle(new ListarEspecimenesDeGrupoInput(
                tipo: ListarEspecimenesDeGrupoInput::TIPO_LOCALIDAD,
                verbatim: $this->items[$idx]['verbatim'],
                limite: 2000,
            ));

            $this->miembros[$idx] = array_map(fn ($m) => [
                'id' => $m['id'],
                'codigoCatalogo' => $m['codigoCatalogo'],
                'taxonNombre' => $m['taxonNombre'] ?? null,
                'localidad' => $m['localidad'],
                'fechaColecta' => $m['fechaColecta'] ?? null,
                'fechaVerbatim' => $m['fechaVerbatim'] ?? null,
                'colector' => $m['colector'],
            ], $output->items);
            $this->seleccion[$idx] = array_map(fn ($m) => $m['id'], $output->items);
            $this->expandido[$idx] = true;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function seleccionarTodos(int $idx): void
    {
        $this->seleccion[$idx] = array_map(fn ($m) => $m['id'], $this->miembros[$idx] ?? []);
    }

    public function limpiarSeleccion(int $idx): void
    {
        $this->seleccion[$idx] = [];
    }

    /** Busca localidades canónicas más allá de los candidatos sugeridos (reasignar a otro nombre). */
    public function buscarOtros(BuscarLocalidadesPorNombreHandler $handler, int $idx): void
    {
        $consulta = trim($this->busquedaTexto[$idx] ?? '');
        if ($consulta === '') {
            $this->busquedaResultados[$idx] = [];

            return;
        }

        try {
            $output = $handler->handle(new BuscarLocalidadesPorNombreInput(consulta: $consulta));
            $this->busquedaResultados[$idx] = $output->items;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function confirmar(
        ConfirmarLocalidadCanonicaParaVerbatimHandler $handler,
        ListarLocalidadVerbatimsPendientesHandler $listHandler,
        int $idx,
    ): void {
        if (! isset($this->items[$idx])) {
            return;
        }
        $row = $this->items[$idx];
        if (($row['localidadSeleccionada'] ?? '') === '') {
            $this->errorMessage = 'Selecciona una localidad canónica antes de confirmar.';

            return;
        }

        $ids = null;
        if (! empty($this->expandido[$idx])) {
            $ids = array_values($this->seleccion[$idx] ?? []);
            if ($ids === []) {
                $this->errorMessage = 'Selecciona al menos un espécimen, o cierra el detalle para aplicar a todo el grupo.';

                return;
            }
            // Si el grupo se truncó al cargar (hay más miembros que los mostrados)
            // y están TODOS los cargados marcados, aplica al grupo completo por
            // verbatim (ids=null) para no dejar en silencio a los no cargados.
            $cargados = count($this->miembros[$idx] ?? []);
            if (count($ids) === $cargados && $cargados < (int) ($this->items[$idx]['totalEspecimenes'] ?? $cargados)) {
                $ids = null;
            }
        }

        try {
            $output = $handler->handle(new ConfirmarLocalidadCanonicaParaVerbatimInput(
                verbatim: $row['verbatim'],
                localidadId: $row['localidadSeleccionada'],
                especimenIds: $ids,
            ));

            $this->successMessage = "Enlazados {$output->especimenesEnlazados} espécimen(es) a la localidad seleccionada.";
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Crea una localidad canónica NUEVA a partir del nombre tecleado y enlaza
     * en el acto los especímenes del grupo (todo o solo lo seleccionado). Evita
     * tener que salir a la pantalla de Localidades a registrarla primero.
     */
    public function crearYEnlazar(
        CrearLocalidadYEnlazarVerbatimHandler $handler,
        ListarLocalidadVerbatimsPendientesHandler $listHandler,
        int $idx,
    ): void {
        if (! isset($this->items[$idx])) {
            return;
        }
        $nombre = trim($this->nuevaLocalidadNombre[$idx] ?? '');
        if ($nombre === '') {
            $this->errorMessage = 'Escribe el nombre de la nueva localidad antes de crearla.';

            return;
        }
        $rango = trim($this->nuevaLocalidadRango[$idx] ?? '') ?: RangoLocalidad::Sitio->value;

        $ids = null;
        if (! empty($this->expandido[$idx])) {
            $ids = array_values($this->seleccion[$idx] ?? []);
            if ($ids === []) {
                $this->errorMessage = 'Selecciona al menos un espécimen, o cierra el detalle para aplicar a todo el grupo.';

                return;
            }
            // Si el grupo se truncó al cargar (hay más miembros que los mostrados)
            // y están TODOS los cargados marcados, aplica al grupo completo por
            // verbatim (ids=null) para no dejar en silencio a los no cargados.
            $cargados = count($this->miembros[$idx] ?? []);
            if (count($ids) === $cargados && $cargados < (int) ($this->items[$idx]['totalEspecimenes'] ?? $cargados)) {
                $ids = null;
            }
        }

        try {
            $output = $handler->handle(new CrearLocalidadYEnlazarVerbatimInput(
                verbatim: $this->items[$idx]['verbatim'],
                nombreCanonico: $nombre,
                rango: $rango,
                especimenIds: $ids,
            ));

            $this->successMessage = "Creada «{$output->nombreCanonico}» y enlazados {$output->especimenesEnlazados} espécimen(es).";
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        $totalPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);

        return view('inventariogestioncoleccion::admin.taxonomia.localidades.revision', [
            'totalPaginas' => $totalPaginas,
            'inicio' => $this->total > 0 ? ($this->pagina - 1) * $this->porPagina + 1 : 0,
            'fin' => min($this->pagina * $this->porPagina, $this->total),
            'rangosLocalidad' => RangoLocalidad::valoresAceptados(),
        ]);
    }
}
