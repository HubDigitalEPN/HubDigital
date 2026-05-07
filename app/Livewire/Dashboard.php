<?php

namespace App\Livewire;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        return match (Auth::user()->rol) {
            RolUsuario::CURADOR     => view('livewire.dashboard.curador-panel'),
            RolUsuario::PRESTAMISTA => view('livewire.dashboard.prestamista-panel'),
            RolUsuario::DEPOSITANTE => view('livewire.dashboard.depositante-panel'),
        };
    }
}
