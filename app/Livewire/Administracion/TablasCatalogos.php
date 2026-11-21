<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithInteractiveGrid;
use App\Support\AccesoCatalogo;
use App\Support\RegistrosAdministracionCatalogos;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\CatalogoPublico\Presentation\Http\Controllers\AdministrarAsistente;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ConfiguracionMatrizDeposito;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ConfiguracionColumnasIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ConfiguracionPlantillaCatalogoIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DatasetConfigForm;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\LocalidadIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonIndex;

#[Layout('layouts.app')]
#[Title('Tablas de catálogos')]
final class TablasCatalogos extends Component
{
    use WithInteractiveGrid;
    use WithPagination;

    #[Url(history: true)]
    public string $tabla = '';

    #[Url(as: 'q')]
    public string $busqueda = '';

    public string $tablaNueva = 'instituciones';

    #[Locked]
    public array $editor = [];

    #[Locked]
    public int $versionEditor = 0;

    #[Locked]
    public bool $gestionaMatrices = false;

    public function mount(): void
    {
        $this->gestionaMatrices = request()->routeIs('admin.matrices');
        $this->tablaNueva = $this->gestionaMatrices ? 'matriz-depositos' : 'instituciones';
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function updatedTabla(): void
    {
        $this->resetPage();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function nuevo(): void
    {
        $catalogos = $this->catalogos();
        if (isset($catalogos[$this->tabla]['nuevo'])) {
            $this->mostrarEditor($this->tabla, $catalogos[$this->tabla]['nuevo']);
            return;
        }
        $this->modal('catalogo-alta')->show();
    }

    public function abrirAlta(): void
    {
        $catalogo = $this->catalogos()[$this->tablaNueva] ?? null;
        abort_unless(isset($catalogo['nuevo']), 403);
        $this->modal('catalogo-alta')->close();
        $this->mostrarEditor($this->tablaNueva, $catalogo['nuevo']);
    }

    public function abrirRegistro(string $tabla, string $id, string $accion = 'editar'): void
    {
        $catalogo = $this->catalogos()[$tabla] ?? null;
        abort_unless($catalogo && in_array($accion, ['editar', 'estado', 'acta'], true), 403);
        abort_if(mb_strlen($id) > 200, 404);
        $fila = app(RegistrosAdministracionCatalogos::class)->consultar([$tabla => $catalogo['nombre']], '')
            ->where('id', $id)->first();
        abort_unless($fila, 404);

        $metodo = $catalogo[$accion] ?? null;
        $argumento = match ($accion) {
            'acta' => $fila->acta_id,
            'estado' => $tabla === 'instituciones' ? $fila->estado_id : $fila->id,
            default => $fila->id,
        };
        abort_if($accion !== 'editar' && ($metodo === null || $argumento === null), 403);
        $this->mostrarEditor($tabla, $metodo, $argumento);
    }

    public function cerrarEditor(): void
    {
        $this->editor = [];
    }

    #[On('registro-catalogo-actualizado')]
    public function actualizar(): void
    {
        $this->cerrarEditor();
        session()->flash('catalogo-actualizado', 'Transacción Efectuada');
    }

    public function render(): View
    {
        $catalogos = $this->catalogos();
        abort_unless($this->tabla === '' || isset($catalogos[$this->tabla]), 403);
        abort_unless($this->editor === [] || isset($catalogos[$this->editor['tabla']]), 403);
        $nombres = array_map(static fn (array $catalogo): string => $catalogo['nombre'], $catalogos);
        if ($this->tabla !== '') {
            $nombres = [$this->tabla => $nombres[$this->tabla]];
        }
        $consulta = app(RegistrosAdministracionCatalogos::class)->consultar($nombres, mb_substr($this->busqueda, 0, 150));
        $this->ordenarConsultaGrilla($consulta, $this->tabla === '' ? 'catalogo' : 'nombre');
        $filas = $this->paginarGrilla($consulta->orderBy('nombre')->orderBy('tabla')->orderBy('id'));

        return view('livewire.administracion.tablas-catalogos', compact('catalogos', 'filas'))
            ->title($this->gestionaMatrices ? 'Matrices' : 'Tablas de catálogos');
    }

    protected function gridColumns(): array
    {
        return ['nombre' => 'nombre', 'catalogo' => 'catalogo', 'detalle' => 'detalle', 'estado' => 'estado'];
    }

    private function mostrarEditor(string $tabla, ?string $accion, ?string $argumento = null): void
    {
        $this->editor = compact('tabla', 'accion', 'argumento');
        $this->versionEditor++;
        if ($this->catalogos()[$tabla]['panel'] ?? false) {
            $this->modal('catalogo-configuracion')->show();
        }
    }

    private function catalogos(): array
    {
        if ($this->gestionaMatrices) {
            return $this->matrices();
        }
        $catalogos = [
            'instituciones' => ['nombre' => 'Instituciones y depositantes', 'componente' => InstitucionesCatalogo::class,
                'editar' => 'editarDirectorio', 'nuevo' => 'nueva', 'estado' => 'cambiarEstado', 'acta' => 'generarActa'],
            'cargos' => ['nombre' => 'Cargos', 'componente' => CargosCatalogo::class,
                'editar' => 'editar', 'nuevo' => 'nueva', 'estado' => 'cambiarEstado'],
            'grupos-animales' => ['nombre' => 'Grupos animales', 'componente' => GruposAnimalesCatalogo::class,
                'editar' => 'editar', 'nuevo' => 'nuevo', 'estado' => 'cambiarEstado'],
            'taxones' => ['nombre' => 'Taxones', 'componente' => TaxonIndex::class,
                'editar' => 'abrirEditModal', 'nuevo' => 'abrirModal', 'estado' => 'desactivarTaxon'],
            'localidades-coleccion' => ['nombre' => 'Localidades de la colección', 'componente' => LocalidadIndex::class,
                'editar' => 'abrirEditModal', 'nuevo' => 'abrirModal'],
            'fuentes-revocacion' => ['nombre' => 'Revocación de firmas', 'componente' => FuentesRevocacionFirma::class,
                'editar' => 'editar', 'nuevo' => 'nuevo', 'estado' => 'cambiarEstado'],
            'asistente' => ['nombre' => 'Asistente del portal', 'componente' => AdministrarAsistente::class, 'panel' => true],
            'gbif' => ['nombre' => 'Publicación GBIF', 'componente' => DatasetConfigForm::class, 'panel' => true],
            'columnas' => ['nombre' => 'Columnas de la tabla', 'componente' => ConfiguracionColumnasIndex::class, 'panel' => true],
        ];
        if (auth()->user()->esAdministrador()) {
            $catalogos += [
                'localidades-ecuador' => ['nombre' => 'Localidades de Ecuador', 'componente' => LocalidadesEcuadorCatalogo::class,
                    'editar' => 'editar', 'nuevo' => 'nueva', 'estado' => 'cambiarEstado'],
                'botones' => ['nombre' => 'Botones y permisos', 'componente' => GestionBotones::class, 'panel' => true],
                'sistema' => ['nombre' => 'Correo y descargas', 'componente' => ConfiguracionSistema::class, 'panel' => true],
                'siglas-expedientes' => ['nombre' => 'Siglas de expedientes', 'componente' => ConfiguracionExpediente::class, 'panel' => true],
            ];
        }

        return $catalogos;
    }

    private function matrices(): array
    {
        $matrices = [
            'matriz-depositos' => ['nombre' => 'Matriz de depósitos', 'componente' => ConfiguracionMatrizDeposito::class,
                'editar' => 'editar', 'nuevo' => 'editar'],
        ];
        if (AccesoCatalogo::completo()) {
            $matrices['plantilla-catalogo'] = ['nombre' => 'Plantilla de importación del catálogo', 'componente' => ConfiguracionPlantillaCatalogoIndex::class, 'panel' => true];
        }
        if (auth()->user()->esAdministrador()) {
            $matrices['formato-ipt'] = ['nombre' => 'Formato IPT (GBIF)', 'componente' => ConfiguracionFormatoIpt::class, 'panel' => true];
        }

        return $matrices;
    }
}
