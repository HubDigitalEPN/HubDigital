<?php

namespace App\Livewire\Auth;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Register extends Component
{
    public string $role = 'prestamista';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|min:8')]
    public string $password = '';

    #[Validate('required|same:password')]
    public string $password_confirmation = '';

    public function submit(): void
    {
        $this->validate();

        if (! in_array($this->role, ['prestamista', 'depositante'])) {
            $this->addError('role', 'Selecciona tu propósito para continuar.');

            return;
        }

        try {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'rol' => RolUsuario::from(strtoupper($this->role)),
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->addError('form', 'No se pudo completar el registro. Verifica los datos e intenta de nuevo.');

            return;
        }

        Auth::login($user);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
