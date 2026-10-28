<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Support\BotonesSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Botones y permisos por perfil')]
final class GestionBotones extends Component
{
    public array $permisos = [];
    #[\Livewire\Attributes\Locked]
    public string $revision = '';

    public function boot(): void
    {
        request()->attributes->set('administracion_sensible', true);
        $this->autorizar();
    }

    private function autorizar(): void
    {
        abort_unless(auth()->check() && User::query()->whereKey(auth()->id())
            ->whereNotNull('email_verified_at')->where('must_change_password', false)
            ->whereHas('roles', fn ($roles) => $roles->where('rol', RolUsuario::ADMIN->value))->exists(), 403);
    }

    public function mount(): void
    {
        $this->cargar();
    }

    private function cargar(): void
    {
        $this->permisos = [];
        foreach (BotonesSistema::definiciones() as $clave => $def) {
            foreach (RolUsuario::cases() as $rol) $this->permisos[str_replace('.', '_', $clave)][$rol->value] = BotonesSistema::permisos($clave, $rol);
        }
        $this->revision = $this->huella();
    }

    private function huella(): string
    {
        return hash('sha256', DB::table('usuarios.permisos_botones')->orderBy('boton')->orderBy('rol')->get()->toJson());
    }

    public function guardar(): void
    {
        $this->autorizar();
        DB::transaction(function (): void {
            // Serializa administradores; incluye la primera escritura con la tabla vacía.
            DB::statement('LOCK TABLE usuarios.permisos_botones IN EXCLUSIVE MODE');
            if (! hash_equals($this->huella(), $this->revision)) {
                throw ValidationException::withMessages(['permisos' => 'Otro administrador modificó los permisos. Recarga la página antes de guardar.']);
            }
            foreach (BotonesSistema::definiciones() as $clave => $def) {
                foreach (RolUsuario::cases() as $rol) {
                    $valor = $this->permisos[str_replace('.', '_', $clave)][$rol->value] ?? null;
                    if (! is_array($valor) || ! is_bool($valor['ver'] ?? null) || ! is_bool($valor['usar'] ?? null)) {
                        throw ValidationException::withMessages(['permisos' => 'Completa la matriz con permisos válidos.']);
                    }
                    if (($valor['ver'] || $valor['usar']) && ! in_array($rol->value, $def['elegibles'], true)) {
                        throw ValidationException::withMessages(['permisos' => 'La descarga interna y los controles curatoriales requieren un perfil del equipo.']);
                    }
                    if ($valor['usar'] && ! $valor['ver']) {
                        throw ValidationException::withMessages(['permisos' => 'Para usar un botón también debe estar visible.']);
                    }
                    DB::table('usuarios.permisos_botones')->updateOrInsert(['boton' => $clave, 'rol' => $rol->value], [
                        'ver' => $valor['ver'], 'usar' => $valor['usar'],
                        'actualizado_por' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
        $this->cargar();
        session()->flash('botones-guardados', 'Permisos guardados. Se aplican a cada perfil activo.');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.administracion.gestion-botones', ['botones' => BotonesSistema::definiciones(), 'roles' => RolUsuario::cases()]);
    }
}
