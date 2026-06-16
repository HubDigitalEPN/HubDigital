<?php

namespace App\Livewire\Auth;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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

        if ($this->remember) {
            $this->aplicarDuracionRecordarme();
        }

        // Cuenta pendiente de verificación: no accede a funcionalidades
        // protegidas; se le envía al aviso para completar la verificación
        // o reenviar el correo (criterios 5, 8, 10).
        if (! $user->hasVerifiedEmail()) {
            $this->redirect(route('verification.notice'), navigate: false);

            return;
        }

        $destination = match ($user->rol) {
            RolUsuario::DEPOSITANTE => route('prestamos.investigador.deposito.crear'),
            RolUsuario::PRESTAMISTA => route('prestamos.investigador.mis-solicitudes'),
            RolUsuario::CURADOR => route('dashboard'),
            default => route('dashboard'),
        };

        $this->redirect($destination, navigate: true);
    }

    /**
     * Reemplaza la expiración permanente (5 años) que Laravel asigna a la
     * cookie de "recordarme" por el tiempo configurable en config/auth.php.
     * Reutiliza el recaller ya generado por Auth::login y solo cambia su TTL,
     * conservando nombre, valor y atributos de seguridad (secure/httpOnly/etc.).
     */
    private function aplicarDuracionRecordarme(): void
    {
        $minutes = (int) config('auth.remember_lifetime');

        if ($minutes <= 0) {
            return; // Conserva el comportamiento permanente por defecto de Laravel.
        }

        $recaller = Cookie::getFacadeRoot()->queued(Auth::guard('web')->getRecallerName());

        if ($recaller === null) {
            return;
        }

        Cookie::queue(Cookie::make($recaller->getName(), $recaller->getValue(), $minutes));
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
