<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Enums\RolUsuario;
use App\Support\MenuLateral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Menú lateral')]
final class GestionMenuLateral extends Component
{
    private const TODAS_LAS_OPCIONES = 'todas';

    #[Url(as: 'rol', history: true)]
    public string $rolVista = '';

    #[Locked]
    public ?string $edicion = null;

    public string $tipo = 'enlace';
    public string $texto = '';
    public string $ruta = '';
    public string $grupo = '';
    public array $roles = [];
    public bool $visible = true;

    public function boot(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
    }

    public function mount(): void
    {
        if ($this->rolVista !== self::TODAS_LAS_OPCIONES) {
            $this->rolVista = (RolUsuario::desdeHistorico($this->rolVista) ?? auth()->user()->rolActivo())->value;
        }
    }

    public function seleccionarRol(string $rol): void
    {
        abort_unless($rol === self::TODAS_LAS_OPCIONES || RolUsuario::tryFrom($rol) !== null, 422);
        $this->rolVista = $rol;
    }

    public function nueva(): void
    {
        $this->reset('edicion', 'tipo', 'texto', 'ruta', 'grupo', 'roles', 'visible');
        $this->resetValidation();
        $this->modal('editor-menu-lateral')->show();
    }

    public function editar(string $clave, MenuLateral $menu): void
    {
        $opcion = $menu->opciones()[$clave] ?? abort(404);
        $this->edicion = $clave;
        $this->tipo = $opcion['ruta'] === null ? 'grupo' : 'enlace';
        $this->texto = $opcion['texto'];
        $this->ruta = $opcion['ruta'] ?? '';
        $this->grupo = $opcion['grupo'] ?? '';
        $this->roles = $opcion['roles'];
        $this->visible = $opcion['visible'];
        $this->resetValidation();
        $this->modal('editor-menu-lateral')->show();
    }

    public function guardar(MenuLateral $menu): void
    {
        $this->texto = trim($this->texto);

        DB::transaction(function () use ($menu): void {
            DB::statement('lock table usuarios.menu_lateral in share row exclusive mode');
            $opciones = $menu->opciones();
            $destinos = $menu->destinos();
            $original = $this->edicion === null ? null : ($opciones[$this->edicion] ?? abort(404));
            if ($original) {
                $this->tipo = $original['ruta'] === null ? 'grupo' : 'enlace';
            }
            if ($this->tipo === 'grupo') {
                $this->ruta = $this->grupo = '';
            }
            $grupos = array_keys(array_filter($opciones, static fn (array $opcion): bool => $opcion['ruta'] === null));
            $permitidos = $this->tipo === 'grupo'
                ? array_column(RolUsuario::cases(), 'value')
                : ($destinos[$this->ruta]['rolesPermitidos'] ?? []);

            $this->validate([
                'tipo' => ['required', Rule::in(['grupo', 'enlace'])],
                'texto' => ['required', 'string', 'max:80'],
                'ruta' => ['required_if:tipo,enlace', 'nullable', Rule::in(array_keys($destinos))],
                'grupo' => ['nullable', Rule::in($grupos)],
                'roles' => ['required', 'array', 'min:1'],
                'roles.*' => ['required', 'distinct', Rule::in($permitidos)],
                'visible' => ['boolean'],
            ], [
                'roles.required' => 'Selecciona al menos un rol.',
                'roles.*.in' => 'El rol seleccionado no tiene acceso a este destino.',
            ]);

            $menu->guardar($this->edicion ?? Str::orderedUuid()->toString(), [
                'texto' => $this->texto,
                'ruta' => $this->ruta ?: null,
                'grupo' => $this->grupo ?: null,
                'roles' => array_values($this->roles),
                'visible' => $this->visible,
            ]);
        });

        $this->modal('editor-menu-lateral')->close();
        $this->volverAlMenu();
    }

    public function updatedRuta(): void
    {
        $permitidos = app(MenuLateral::class)->destinos()[$this->ruta]['rolesPermitidos'] ?? [];
        $this->roles = array_values(array_intersect($this->roles, $permitidos));
    }

    public function eliminar(string $clave, MenuLateral $menu): void
    {
        DB::transaction(function () use ($clave, $menu): void {
            DB::statement('lock table usuarios.menu_lateral in share row exclusive mode');
            $opciones = $menu->opciones();
            abort_unless(isset($opciones[$clave]), 404);
            if (in_array($clave, array_column($opciones, 'grupo'), true)) {
                throw ValidationException::withMessages(['eliminar' => 'Mueve o elimina primero las opciones que pertenecen a este grupo.']);
            }
            $menu->guardar($clave, ['eliminada' => true]);
        });

        $this->volverAlMenu();
    }

    public function render(MenuLateral $menu): View
    {
        $opciones = $menu->opciones();
        $grupos = array_filter($opciones, static fn (array $opcion): bool => $opcion['ruta'] === null);
        if ($this->rolVista === self::TODAS_LAS_OPCIONES) {
            $raices = $hijosPorGrupo = [];
            foreach ($opciones as $clave => $opcion) {
                $padre = $opcion['grupo'] ?? '';
                if (isset($grupos[$padre])) {
                    $hijosPorGrupo[$padre][$clave] = $opcion;
                } else {
                    $raices[$clave] = $opcion;
                }
            }
        } else {
            $rol = RolUsuario::tryFrom($this->rolVista) ?? auth()->user()->rolActivo();
            $raices = $menu->visiblePara($rol, $opciones);
            $hijosPorGrupo = array_filter(array_map(static fn (array $opcion): array => $opcion['hijos'], $raices));
        }
        $rolesVista = array_combine(array_column(RolUsuario::cases(), 'value'),
            array_map(static fn (RolUsuario $rol): string => $rol->etiqueta(), RolUsuario::cases()));
        $rolesVista[self::TODAS_LAS_OPCIONES] = 'Todas las opciones';
        $gruposAbiertos = array_keys(array_filter($hijosPorGrupo, static fn (array $hijos): bool =>
            in_array('admin.menu-lateral', array_column($hijos, 'ruta'), true)));
        $destinos = $menu->destinos();
        $rolesDisponibles = $this->tipo === 'grupo'
            ? RolUsuario::cases()
            : ($destinos[$this->ruta]['roles'] ?? []);

        return view('livewire.administracion.gestion-menu-lateral', compact(
            'raices', 'hijosPorGrupo', 'grupos', 'rolesVista', 'gruposAbiertos', 'destinos', 'rolesDisponibles',
        ));
    }

    private function volverAlMenu(): void
    {
        $parametros = $this->rolVista === auth()->user()->rolActivo()->value ? [] : ['rol' => $this->rolVista];
        $this->redirectRoute('admin.menu-lateral', $parametros, navigate: true);
    }
}
