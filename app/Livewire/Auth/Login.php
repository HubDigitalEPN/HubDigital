<?php

namespace App\Livewire\Auth;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function submit(): void
    {
        $this->validate();

        $user = User::where('email', $this->email)->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            $this->addError('email', 'Credenciales inválidas. Verifique su correo y contraseña.');

            return;
        }

        Auth::login($user, $this->remember);

        $destination = match ($user->rol) {
            RolUsuario::DEPOSITANTE => route('prestamos.investigador.deposito.crear'),
            RolUsuario::PRESTAMISTA => route('prestamos.investigador.mis-solicitudes'),
            RolUsuario::CURADOR => route('prestamos.curador.solicitudes'),
            default => route('dashboard'),
        };

        $this->redirect($destination, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
