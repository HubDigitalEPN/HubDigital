<?php

namespace App\Livewire;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class SelectorRolActivo extends Component
{
    /**
     * Conmuta el rol activo entre los roles que la cuenta posee y aterriza
     * en la sección correspondiente.
     */
    public function cambiar(string $rol): void
    {
        $user = Auth::user();
        $destino = RolUsuario::tryFrom($rol);

        if ($destino === null || ! $user->tieneRol($destino)) {
            return;
        }

        $user->activarRol($destino);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        $user = Auth::user();
        $roles = $user->rolesAsignados();

        // Si la cuenta tiene un solo rol auto-servicio (y no es curador),
        // ofrece activar el rol complementario desde el propio menú.
        $rolActivable = null;
        if (! $user->esCurador() && $roles->count() === 1) {
            $rolActivable = $user->esDepositante()
                ? RolUsuario::PRESTAMISTA
                : RolUsuario::DEPOSITANTE;
        }

        return view('livewire.selector-rol-activo', [
            'roles' => $roles,
            'rolActivo' => $user->rolActivo(),
            'rolActivable' => $rolActivable,
        ]);
    }
}
