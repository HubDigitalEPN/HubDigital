<?php

namespace App\Livewire\Auth;

use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Register extends Component
{
    #[Locked]
    public string $rolInicial = 'PRESTAMISTA';

    public function mount(): void
    {
        $solicitado = old('rol', request()->query('rol', 'PRESTAMISTA'));
        $rol = is_string($solicitado) ? strtoupper(trim($solicitado)) : '';
        $this->rolInicial = in_array($rol, ['PRESTAMISTA', 'DEPOSITANTE'], true) ? $rol : 'PRESTAMISTA';
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
