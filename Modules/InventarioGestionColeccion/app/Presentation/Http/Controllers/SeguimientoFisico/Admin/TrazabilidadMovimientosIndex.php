<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen\ConsultarTrazabilidadEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen\ConsultarTrazabilidadEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables\ListarEspecimenesAsignablesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables\ListarEspecimenesAsignablesInput;

/**
 * Pantalla del curador para consultar la línea de tiempo de un espécimen: busca el espécimen por
 * código o nombre científico, lo selecciona y ve su historial cronológico de movimientos, incluidos
 * los de los contenedores (unit tray, caja) que lo albergan, con su responsable. Es presentación
 * pura: delega la búsqueda y la consulta de trazabilidad en sus casos de uso.
 */
#[Layout('layouts.app', params: ['title' => 'Trazabilidad de movimientos'])]
final class TrazabilidadMovimientosIndex extends Component
{
    /** Etiquetas legibles por tipo de evento; el resto se humaniza automáticamente. */
    private const ETIQUETA_TIPO = [
        'especimen_reubicado' => 'Espécimen reubicado',
        'unit_tray_reubicado' => 'Unit tray reubicado',
        'caja_ingresada' => 'Caja ingresada en ranura',
        'caja_extraida' => 'Caja extraída de su ranura',
    ];

    public string $busqueda = '';

    /** @var array<int, array{id: string, codigoCatalogo: string, taxonNombre: string}> */
    public array $candidatos = [];

    public string $especimenSeleccionado = '';

    public ?string $especimenSeleccionadoLabel = null;

    /** @var array<int, array{tipo: string, origen: ?string, destino: ?string, fecha: string, responsable: string}> */
    public array $movimientos = [];

    public bool $consultado = false;

    /** Rebusca candidatos acotados al teclear (nunca trae el catálogo completo de 48k+). */
    public function updatedBusqueda(ListarEspecimenesAsignablesHandler $handler): void
    {
        $termino = trim($this->busqueda);
        $this->candidatos = $termino === ''
            ? []
            : $handler->handle(new ListarEspecimenesAsignablesInput(busqueda: $termino, limite: 15))->items;
    }

    /** Selecciona un espécimen (de la lista de candidatos) y carga su línea de tiempo. */
    public function seleccionarEspecimen(string $id, ConsultarTrazabilidadEspecimenHandler $handler): void
    {
        $elegido = array_values(array_filter($this->candidatos, fn ($c) => $c['id'] === $id))[0] ?? null;
        if ($elegido === null) {
            return;
        }

        $this->especimenSeleccionado = $id;
        $this->especimenSeleccionadoLabel = trim("{$elegido['codigoCatalogo']} — {$elegido['taxonNombre']}", ' —');
        $this->candidatos = [];
        $this->busqueda = '';
        $this->consultado = true;

        $output = $handler->handle(new ConsultarTrazabilidadEspecimenInput(especimenId: $id));
        $this->movimientos = array_map(fn ($m) => [
            'tipo' => self::ETIQUETA_TIPO[$m->tipo] ?? ucfirst(str_replace('_', ' ', $m->tipo)),
            'origen' => $m->origen,
            'destino' => $m->destino,
            'fecha' => $m->ocurridoEn->format('d/m/Y H:i'),
            'responsable' => ucfirst($m->responsable),
        ], $output->movimientos);
    }

    public function limpiar(): void
    {
        $this->reset('busqueda', 'candidatos', 'especimenSeleccionado', 'especimenSeleccionadoLabel', 'movimientos', 'consultado');
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.trazabilidad.index');
    }
}
