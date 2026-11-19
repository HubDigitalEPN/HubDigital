<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithInteractiveGrid;
use App\Services\DirectorioInstituciones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app')]
#[Title('Instituciones y depositantes')]
final class InstitucionesCatalogo extends Component
{
    use WithInteractiveGrid;
    use WithPagination;
    use TraduceErroresPersistencia;

    public string $busqueda = '';
    public string $busquedaPendientes = '';
    #[Locked] public ?int $editandoId = null;
    #[Locked] public ?string $entidadId = null;
    #[Locked] public ?string $filaId = null;
    #[Locked] public string $revisionEdicion = '';
    public string $nombre = '';
    public string $tipo = 'institucion';
    public string $contacto = '';
    public bool $usarEnSolicitudes = true;
    public bool $usarEnColeccion = true;
    public ?string $errorMessage = null;
    public bool $showActaModal = false;
    public string $actaEntidadNombre = '';
    public ?string $actaPdfRuta = null;

    public function boot(): void { abort_unless(auth()->user()?->esCurador(), 403); }
    public function updatedBusqueda(): void { $this->resetPage(); }

    public function nueva(): void
    {
        $this->reset('editandoId','entidadId','filaId','revisionEdicion','nombre','tipo','contacto','usarEnSolicitudes','usarEnColeccion','errorMessage');
        $this->resetValidation();
        $this->modal('institucion-editor')->show();
    }

    /** Conserva los controles que ya usaban el identificador del portal. */
    public function editar(int $id): void
    {
        $fila = app(DirectorioInstituciones::class)->consulta()->where('institucion_id',$id)->first();
        abort_unless($fila,404);
        $this->editarDirectorio($fila->id);
    }

    public function editarDirectorio(string $id): void
    {
        $servicio = app(DirectorioInstituciones::class);
        $fila = $servicio->consulta()->where('id',$id)->first();
        abort_unless($fila,404);
        $this->filaId=$fila->id; $this->editandoId=$fila->institucion_id; $this->entidadId=$fila->entidad_id;
        $this->revisionEdicion=$servicio->revision($fila);
        $this->nombre=$fila->nombre; $this->tipo=$fila->tipo ?? ''; $this->contacto=$fila->contacto ?? '';
        $this->usarEnSolicitudes=(bool)$fila->activo; $this->usarEnColeccion=$fila->entidad_id !== null;
        $this->resetValidation(); $this->errorMessage=null;
        $this->modal('institucion-editor')->show();
    }

    public function guardar(): void
    {
        $this->validate(['nombre'=>['required','string','min:'.($this->usarEnSolicitudes || $this->editandoId ? 3 : 1),'max:'.($this->usarEnSolicitudes || $this->editandoId ? 160 : 255)],
            'tipo'=>'nullable|in:institucion,persona','contacto'=>'nullable|string|max:255',
            'usarEnSolicitudes'=>'boolean','usarEnColeccion'=>'boolean']);
        if (!$this->filaId && !$this->usarEnSolicitudes && !$this->usarEnColeccion) {
            $this->addError('nombre','Selecciona al menos uno de los usos de esta institución o depositante.'); return;
        }
        $nombre = app(DirectorioInstituciones::class)->guardar($this->filaId,$this->revisionEdicion,[
            'nombre'=>$this->nombre,'tipo'=>$this->tipo,'contacto'=>trim($this->contacto),
            'solicitudes'=>$this->usarEnSolicitudes,'coleccion'=>$this->usarEnColeccion]);
        $this->modal('institucion-editor')->close();
        $this->busqueda=$nombre; $this->resetPage();
        session()->flash('institucion-guardada','Institución o depositante guardado.');
    }

    public function cambiarEstado(int $id): void
    {
        $fila=DB::table('usuarios.instituciones_catalogo')->find($id); abort_unless($fila,404);
        DB::table('usuarios.instituciones_catalogo')->where('id',$id)->update(['activo'=>!$fila->activo,'updated_at'=>now()]);
    }

    public function generarActa(string $id, GenerarActaEntregaHandler $handler): void
    {
        $this->errorMessage=null; $this->actaPdfRuta=null;
        try {
            $out=$handler->handle(new GenerarActaEntregaInput(entidadDepositanteId:$id));
            $this->actaEntidadNombre=$out->entidadNombre; $this->actaPdfRuta=$out->pdfRuta; $this->showActaModal=true;
        } catch (\Throwable $e) { $this->errorMessage=$this->traducirErrorParaUsuario($e); }
    }

    public function render(): View
    {
        $query=app(DirectorioInstituciones::class)->consulta();
        if (trim($this->busqueda)!=='') $query->where('nombre','ilike','%'.trim($this->busqueda).'%');
        return view('livewire.administracion.instituciones-catalogo',[
            'instituciones'=>$this->paginarGrilla($this->ordenarConsultaGrilla($query,'nombre')->orderBy('id')),
            'pendientes'=>Schema::hasTable('usuarios.catalogos_nombres_pendientes')
                ? DB::table('usuarios.catalogos_nombres_pendientes')->where('tipo','institucion')->where('resuelto',false)
                    ->when(trim($this->busquedaPendientes)!=='',fn($q)=>$q->where('valor_original','ilike','%'.trim($this->busquedaPendientes).'%'))->orderBy('valor_original')->get()
                : collect(),
        ]);
    }

    protected function gridColumns(): array
    {
        return ['nombre'=>'nombre','tipo'=>'tipo_label','contacto'=>'contacto','estado'=>'estado','coleccion'=>'coleccion'];
    }
}
