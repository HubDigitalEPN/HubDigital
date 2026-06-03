<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarPermiso\ActualizarPermisoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarPermiso\ActualizarPermisoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisos\ListarPermisosHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso\RegistrarPermisoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso\RegistrarPermisoInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoPermiso;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Permisos'])]
final class PermisoIndex extends Component
{
    use TraduceErroresPersistencia;

    public array $permisos = [];

    // Crear
    public bool $showCreateModal = false;

    #[Rule('required|string|in:research,transport,export_import')]
    public string $tipo = 'research';

    #[Rule('nullable|string|max:255')]
    public string $numero = '';

    #[Rule('nullable|string|max:255')]
    public string $responsable = '';

    #[Rule('nullable|string|max:2000')]
    public string $detalles = '';

    // Editar
    public bool $showEditModal = false;

    public string $editandoId = '';

    public string $editTipo = 'research';

    public string $editNumero = '';

    public string $editResponsable = '';

    public string $editDetalles = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarPermisosHandler $handler): void
    {
        $this->cargar($handler);
    }

    public function cargar(ListarPermisosHandler $handler): void
    {
        try {
            $output = $handler->handle();
            $this->permisos = array_map(fn ($p) => [
                'id' => $p->id,
                'tipo' => $p->tipo,
                'numero' => $p->numero,
                'responsable' => $p->responsable,
                'detalles' => $p->detalles,
            ], $output->items);
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function abrirCreate(): void
    {
        $this->reset('tipo', 'numero', 'responsable', 'detalles', 'errorMessage');
        $this->resetValidation();
        $this->tipo = 'research';
        $this->showCreateModal = true;
    }

    public function registrar(RegistrarPermisoHandler $handler): void
    {
        $this->validate();

        try {
            $handler->handle(new RegistrarPermisoInput(
                tipo: $this->tipo,
                numero: $this->nullable($this->numero),
                responsable: $this->nullable($this->responsable),
                detalles: $this->nullable($this->detalles),
            ));

            $this->showCreateModal = false;
            $this->successMessage = 'Permiso registrado.';
            $this->cargar(app(ListarPermisosHandler::class));
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function abrirEdit(string $id): void
    {
        $permiso = collect($this->permisos)->firstWhere('id', $id);
        if ($permiso === null) {
            return;
        }
        $this->editandoId = $id;
        $this->editTipo = $permiso['tipo'];
        $this->editNumero = (string) ($permiso['numero'] ?? '');
        $this->editResponsable = (string) ($permiso['responsable'] ?? '');
        $this->editDetalles = (string) ($permiso['detalles'] ?? '');
        $this->errorMessage = null;
        $this->showEditModal = true;
    }

    public function actualizar(ActualizarPermisoHandler $handler): void
    {
        try {
            $handler->handle(new ActualizarPermisoInput(
                permisoId: $this->editandoId,
                tipo: $this->editTipo,
                numero: $this->nullable($this->editNumero),
                responsable: $this->nullable($this->editResponsable),
                detalles: $this->nullable($this->editDetalles),
            ));
            $this->showEditModal = false;
            $this->successMessage = 'Permiso actualizado.';
            $this->cargar(app(ListarPermisosHandler::class));
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.permisos.index', [
            'tiposDisponibles' => TipoPermiso::cases(),
        ]);
    }

    private function nullable(string $valor): ?string
    {
        $v = trim($valor);

        return $v !== '' ? $v : null;
    }
}
