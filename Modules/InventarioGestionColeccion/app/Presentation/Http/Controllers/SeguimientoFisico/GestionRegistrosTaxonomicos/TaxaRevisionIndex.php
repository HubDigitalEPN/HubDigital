<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarTaxonesPorNombre\BuscarTaxonesPorNombreHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarTaxonesPorNombre\BuscarTaxonesPorNombreInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim\ConfirmarTaxonCanonicoParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim\ConfirmarTaxonCanonicoParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearTaxonYEnlazarVerbatim\CrearTaxonYEnlazarVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearTaxonYEnlazarVerbatim\CrearTaxonYEnlazarVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo\ListarEspecimenesDeGrupoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo\ListarEspecimenesDeGrupoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes\ListarTaxonVerbatimsPendientesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes\ListarTaxonVerbatimsPendientesInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Taxones por confirmar'])]
final class TaxaRevisionIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * @var list<array{
     *   verbatim: string,
     *   totalEspecimenes: int,
     *   candidatos: list<array{taxonId:string, nombreCientifico:string, rango:string, puntajeSimilitud:float}>,
     *   taxonSeleccionado: string,
     *   taxonSeleccionadoNombre: string,
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

    /** Resultados del buscador: idx => list<array{taxonId,nombreCientifico,rango}>. */
    public array $busquedaResultados = [];

    /** Nombre del taxón nuevo a crear por grupo: idx => string. */
    public array $nuevoTaxonNombre = [];

    /** Rango del taxón nuevo a crear por grupo: idx => string. */
    public array $nuevoTaxonRango = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        $this->cargar($handler);
    }

    public function cargar(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        try {
            $output = $handler->handle(new ListarTaxonVerbatimsPendientesInput(
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
                $output = $handler->handle(new ListarTaxonVerbatimsPendientesInput(
                    limiteCandidatosPorVerbatim: $this->limiteCandidatos,
                    pagina: $this->pagina,
                    porPagina: $this->porPagina,
                ));
            }

            $this->total = $output->totalVerbatimsDistintos;
            $this->items = array_map(fn ($item) => [
                'verbatim' => $item->verbatim,
                'totalEspecimenes' => $item->totalEspecimenes,
                'candidatos' => array_map(fn ($c) => [
                    'taxonId' => $c->taxonId,
                    'nombreCientifico' => $c->nombreCientifico,
                    'rango' => $c->rango,
                    'puntajeSimilitud' => $c->puntajeSimilitud,
                ], $item->candidatos),
                'taxonSeleccionado' => $item->candidatos[0]->taxonId ?? '',
                'taxonSeleccionadoNombre' => $item->candidatos[0]->nombreCientifico ?? '',
            ], $output->items);
            $this->expandido = [];
            $this->miembros = [];
            $this->seleccion = [];
            $this->busquedaTexto = [];
            $this->busquedaResultados = [];
            $this->nuevoTaxonNombre = [];
            $this->nuevoTaxonRango = [];
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        $maxPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
        if ($this->pagina < $maxPaginas) {
            $this->successMessage = null;
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        if ($this->pagina > 1) {
            $this->successMessage = null;
            $this->pagina--;
            $this->cargar($handler);
        }
    }

    public function seleccionarCandidato(int $idx, string $taxonId): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }

        $nombre = '';
        foreach ($this->items[$idx]['candidatos'] ?? [] as $c) {
            if ($c['taxonId'] === $taxonId) {
                $nombre = $c['nombreCientifico'];
                break;
            }
        }
        if ($nombre === '') {
            foreach ($this->busquedaResultados[$idx] ?? [] as $r) {
                if ($r['taxonId'] === $taxonId) {
                    $nombre = $r['nombreCientifico'];
                    break;
                }
            }
        }

        $this->items[$idx]['taxonSeleccionado'] = $taxonId;
        $this->items[$idx]['taxonSeleccionadoNombre'] = $nombre;
    }

    public function verEspecimenes(ListarEspecimenesDeGrupoHandler $handler, int $idx): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }

        if (! empty($this->expandido[$idx])) {
            $this->expandido[$idx] = false;

            return;
        }

        try {
            $output = $handler->handle(new ListarEspecimenesDeGrupoInput(
                tipo: ListarEspecimenesDeGrupoInput::TIPO_TAXON,
                verbatim: $this->items[$idx]['verbatim'],
            ));

            // Conserva TODOS los campos que entrega el handler (código, taxón,
            // localidad, fecha, colector, verbatims…): antes se recortaban a 4 y
            // la vista mostraba fecha/taxón vacíos.
            $this->miembros[$idx] = $output->items;
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

    /** Busca taxones canónicos más allá de los candidatos sugeridos (reasignar a otro nombre). */
    public function buscarOtros(BuscarTaxonesPorNombreHandler $handler, int $idx): void
    {
        $consulta = trim($this->busquedaTexto[$idx] ?? '');
        if ($consulta === '') {
            $this->busquedaResultados[$idx] = [];

            return;
        }

        try {
            $output = $handler->handle(new BuscarTaxonesPorNombreInput(consulta: $consulta));
            $this->busquedaResultados[$idx] = $output->items;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function confirmar(
        ConfirmarTaxonCanonicoParaVerbatimHandler $handler,
        ListarTaxonVerbatimsPendientesHandler $listHandler,
        int $idx,
    ): void {
        if (! isset($this->items[$idx])) {
            return;
        }
        $row = $this->items[$idx];
        if (($row['taxonSeleccionado'] ?? '') === '') {
            $this->errorMessage = 'Selecciona un taxón canónico antes de confirmar.';

            return;
        }

        $ids = null;
        if (! empty($this->expandido[$idx])) {
            $ids = array_values($this->seleccion[$idx] ?? []);
            if ($ids === []) {
                $this->errorMessage = 'Selecciona al menos un espécimen, o cierra el detalle para aplicar a todo el grupo.';

                return;
            }
        }

        try {
            $output = $handler->handle(new ConfirmarTaxonCanonicoParaVerbatimInput(
                verbatim: $row['verbatim'],
                taxonId: $row['taxonSeleccionado'],
                especimenIds: $ids,
            ));

            $this->successMessage = "Enlazados {$output->especimenesEnlazados} espécimen(es) al taxón seleccionado.";
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Crea un taxón canónico NUEVO con el nombre tecleado y enlaza en el acto los
     * especímenes del grupo (todo o solo lo seleccionado). Evita tener que salir a
     * "Gestionar taxones" a registrarlo primero.
     */
    public function crearYEnlazar(
        CrearTaxonYEnlazarVerbatimHandler $handler,
        ListarTaxonVerbatimsPendientesHandler $listHandler,
        int $idx,
    ): void {
        if (! isset($this->items[$idx])) {
            return;
        }
        $nombre = trim($this->nuevoTaxonNombre[$idx] ?? '');
        if ($nombre === '') {
            $this->errorMessage = 'Escribe el nombre científico del nuevo taxón antes de crearlo.';

            return;
        }
        $rango = trim($this->nuevoTaxonRango[$idx] ?? '') ?: RangoTaxonomico::Morfoespecie->value;

        $ids = null;
        if (! empty($this->expandido[$idx])) {
            $ids = array_values($this->seleccion[$idx] ?? []);
            if ($ids === []) {
                $this->errorMessage = 'Selecciona al menos un espécimen, o cierra el detalle para aplicar a todo el grupo.';

                return;
            }
        }

        try {
            $output = $handler->handle(new CrearTaxonYEnlazarVerbatimInput(
                verbatim: $this->items[$idx]['verbatim'],
                nombreCientifico: $nombre,
                rango: $rango,
                especimenIds: $ids,
            ));

            $this->successMessage = "Creado «{$output->nombreCientifico}» y enlazados {$output->especimenesEnlazados} espécimen(es).";
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        $totalPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);

        return view('inventariogestioncoleccion::admin.taxonomia.taxones.revision', [
            'totalPaginas' => $totalPaginas,
            'inicio' => $this->total > 0 ? ($this->pagina - 1) * $this->porPagina + 1 : 0,
            'fin' => min($this->pagina * $this->porPagina, $this->total),
            'rangosTaxon' => RangoTaxonomico::valoresAceptados(),
        ]);
    }
}
