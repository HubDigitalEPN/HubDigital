<?php

namespace App\Livewire\Admin;

use App\Services\Admin\AdminAuthService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin-login')]
class Login extends Component
{
    #[Validate('required')]
    public string $username = '';

    #[Validate('required')]
    public string $password = '';

    public function __construct(
        private readonly AdminAuthService $authService,
    ) {}

    public function login(): void
    {
        $this->validate();

        if ($this->authService->attempt($this->username, $this->password)) {
            session()->regenerate();
            session(['admin_authenticated' => true]);
            $this->redirect(route('admin.dashboard'), navigate: true);

            return;
        }

        $this->addError('username', __('Las credenciales no son correctas.'));
    }

    public function render(): View
    {
        return view('livewire.admin.login');
    }
}
