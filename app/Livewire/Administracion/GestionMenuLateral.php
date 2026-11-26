<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Models\PerfilPersonalizado;
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
    public array $permisos = [];
    public bool $visible = true;

    public function boot(): void
    {
        app(\App\Support\AccesoMenu::class)->autorizar('admin.menu-lateral');
    }

    public function mount(): void
    {
        if ($this->rolVista !== self::TODAS_LAS_OPCIONES) {
            $this->rolVista = (PerfilPersonalizado::resolver($this->rolVista) ?? auth()->user()->rolActivo())->value;
        }
    }

    public function seleccionarRol(string $rol): void
    {
        abort_unless($rol === self::TODAS_LAS_OPCIONES || PerfilPersonalizado::resolver($rol) !== null, 422);
        $this->rolVista = $rol;
    }

    public function nueva(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        $this->reset('edicion', 'tipo', 'texto', 'ruta', 'grupo', 'roles', 'permisos', 'visible');
        $this->resetValidation();
        $this->modal('editor-menu-lateral')->show();
    }

    public function editar(string $clave, MenuLateral $menu): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        $opcion = $menu->opciones()[$clave] ?? abort(404);
        $this->edicion = $clave;
        $this->tipo = $opcion['ruta'] === null ? 'grupo' : 'enlace';
        $this->texto = $opcion['texto'];
        $this->ruta = $opcion['ruta'] ?? '';
        $this->grupo = $opcion['grupo'] ?? '';
        $this->roles = ($opcion['todosLosPerfiles'] ?? false)
            ? PerfilPersonalizado::perfiles()->keys()->all()
            : $opcion['roles'];
        $this->permisos = array_map(fn (array $acciones): array => array_fill_keys($acciones, true), $opcion['permisos'] ?? []);
        $this->visible = $opcion['visible'];
        $this->resetValidation();
        $this->modal('editor-menu-lateral')->show();
    }

    public function guardar(MenuLateral $menu): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        $this->texto = trim($this->texto);

        DB::transaction(function () use ($menu): void {
            DB::statement('lock table usuarios.menu_lateral in share row exclusive mode');
            $opciones = $menu->opciones();
            $perfiles = PerfilPersonalizado::perfiles();
            $destinos = $menu->destinos($perfiles);
            $original = $this->edicion === null ? null : ($opciones[$this->edicion] ?? abort(404));
            if ($original) {
                $this->tipo = $original['ruta'] === null ? 'grupo' : 'enlace';
            }
            if ($this->tipo === 'grupo') {
                $this->ruta = $this->grupo = '';
            }
            $grupos = array_keys(array_filter($opciones, static fn (array $opcion): bool => $opcion['ruta'] === null));
            $permitidos = $this->tipo === 'grupo'
                ? $perfiles->keys()->all()
                : ($destinos[$this->ruta]['rolesPermitidos'] ?? []);

            $this->validate([
                'tipo' => ['required', Rule::in(['grupo', 'enlace'])],
                'texto' => ['required', 'string', 'max:80'],
                'ruta' => ['required_if:tipo,enlace', 'nullable', Rule::in(array_keys($destinos))],
                'grupo' => ['nullable', Rule::in($grupos)],
                'roles' => ['required', 'array', 'min:1'],
                'roles.*' => ['required', 'distinct', Rule::in($permitidos)],
                'permisos.*' => ['array'],
                'permisos.*.*' => ['boolean'],
                'visible' => ['boolean'],
            ], [
                'roles.required' => 'Selecciona al menos un rol.',
                'roles.*.in' => 'El rol seleccionado no tiene acceso a este destino.',
            ]);

            $permisos = [];
            $destino = $destinos[$this->ruta] ?? [];
            foreach (array_intersect($this->roles, $destino['rolesDelegables'] ?? []) as $rol) {
                $acciones = array_intersect_key(array_filter($this->permisos[$rol] ?? []), $destino['accionesDelegables']);
                $permisos[$rol] = array_keys($acciones);
            }

            $menu->guardar($this->edicion ?? Str::orderedUuid()->toString(), [
                'texto' => $this->texto,
                'ruta' => $this->ruta ?: null,
                'grupo' => $this->grupo ?: null,
                'roles' => array_values($this->roles),
                'permisos' => $permisos,
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
        $this->permisos = [];
    }

    public function eliminar(string $clave, MenuLateral $menu): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
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
        $perfiles = PerfilPersonalizado::perfiles();
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
            $rol = $perfiles->get($this->rolVista) ?? auth()->user()->rolActivo();
            $raices = $menu->visiblePara($rol, $opciones);
            $hijosPorGrupo = array_filter(array_map(static fn (array $opcion): array => $opcion['hijos'], $raices));
        }
        $etiquetasRoles = $perfiles->map(fn ($rol): string => $rol->etiqueta())->all();
        $rolesVista = $etiquetasRoles;
        $rolesVista[self::TODAS_LAS_OPCIONES] = 'Todas las opciones';
        $gruposAbiertos = array_keys(array_filter($hijosPorGrupo, static fn (array $hijos): bool =>
            in_array('admin.menu-lateral', array_column($hijos, 'ruta'), true)));
        $destinos = $menu->destinos($perfiles);
        $rolesDisponibles = $this->tipo === 'grupo'
            ? $perfiles->values()->all()
            : ($destinos[$this->ruta]['roles'] ?? []);

        $puedeAdministrar = auth()->user()->esAdministrador();
        return view('livewire.administracion.gestion-menu-lateral', compact(
            'raices', 'hijosPorGrupo', 'grupos', 'rolesVista', 'gruposAbiertos', 'destinos', 'rolesDisponibles', 'etiquetasRoles',
            'puedeAdministrar',
        ));
    }

    private function volverAlMenu(): void
    {
        $parametros = $this->rolVista === auth()->user()->rolActivo()->value ? [] : ['rol' => $this->rolVista];
        $this->redirectRoute('admin.menu-lateral', $parametros, navigate: true);
    }
}
