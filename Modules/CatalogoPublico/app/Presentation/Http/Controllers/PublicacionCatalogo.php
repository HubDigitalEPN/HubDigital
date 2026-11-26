<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Publicación del catálogo')]
final class PublicacionCatalogo extends Component
{
    #[Url]
    public string $vista = 'divulgados';

    public function boot(): void
    {
        app(\App\Support\AccesoMenu::class)->autorizar('divulgacion.index');
    }

    public function render()
    {
        abort_unless(in_array($this->vista, ['divulgados', 'pendientes'], true), 422);

        return view('catalogopublico::livewire.publicacion-catalogo');
    }
}
