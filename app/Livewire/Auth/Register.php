<?php

namespace App\Livewire\Auth;

use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Register extends Component
{
    #[Locked]
    public string $rolInicial = 'DEPOSITANTE';

    public function mount(): void
    {
        $solicitado = old('rol', request()->query('rol', 'DEPOSITANTE'));
        $rol = is_string($solicitado) ? strtoupper(trim($solicitado)) : '';
        $this->rolInicial = in_array($rol, ['DEPOSITANTE', 'DEPOSITANTE'], true) ? $rol : 'DEPOSITANTE';
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
