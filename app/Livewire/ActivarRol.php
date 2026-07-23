<?php

namespace App\Livewire;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Activar rol')]
class ActivarRol extends Component
{
    public string $rol = '';

    #[Validate('required|string|max:255')]
    public string $cargo = '';

    #[Validate('required|string|max:255')]
    public string $institucion = '';

    public function mount(string $rol): void
    {
        $user = Auth::user();
        $destino = RolUsuario::tryFrom(strtoupper($rol));

        // Solo roles auto-servicio; CURADOR nunca es auto-activable.
        if (! in_array($destino, [RolUsuario::PRESTAMISTA, RolUsuario::DEPOSITANTE], true)) {
            abort(404);
        }

        // Una cuenta curador no puede asumir roles auto-servicio.
        if ($user->esCurador()) {
            abort(403);
        }

        $this->rol = $destino->value;

        // Si ya lo posee, solo conmuta el rol activo.
        if ($user->tieneRol($destino)) {
            $user->activarRol($destino);
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        // El rol de solicitante no requiere datos adicionales: se activa directo.
        if ($destino === RolUsuario::PRESTAMISTA) {
            $this->confirmar();

            return;
        }

        // Depositante: prellena cargo/institución si la cuenta ya los tiene.
        $this->cargo = $user->cargo ?? '';
        $this->institucion = $user->institucion ?? '';
    }

    public function confirmar(): void
    {
        $user = Auth::user();
        $destino = RolUsuario::from($this->rol);

        if ($destino === RolUsuario::DEPOSITANTE) {
            $this->validate();
            $user->cargo = $this->cargo;
            $user->institucion = $this->institucion;
            $user->save();
        }

        $user->asignarRol($destino);
        $user->activarRol($destino);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.activar-rol');
    }
}
