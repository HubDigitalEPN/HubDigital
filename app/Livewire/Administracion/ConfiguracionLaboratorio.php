<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Enums\RolUsuario;
use App\Services\PaginaLaboratorio;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Página El Laboratorio')]
final class ConfiguracionLaboratorio extends Component
{
    use WithFileUploads;
    public array $textos = [], $alternativos = [], $fotos = [], $restaurarFotos = [];
    #[Locked] public string $version = '';

    public function boot(): void
    {
        abort_unless(in_array(auth()->user()?->rolActivo(), [RolUsuario::ADMIN, RolUsuario::CURADOR], true), 403);
    }

    public function mount(PaginaLaboratorio $pagina): void
    {
        $datos = $pagina->leer(); $this->textos = $datos['textos']; $this->version = $datos['version'];
        $this->alternativos = [];
        foreach ($datos['fotos'] as $clave => $foto) $this->alternativos[$clave] = $foto['alt'];
    }

    public function guardar(PaginaLaboratorio $pagina): void
    {
        $reglas = ['textos' => ['required', 'array'], 'alternativos' => ['required', 'array'],
            'fotos' => ['array:'.implode(',', array_keys(config('pagina-laboratorio.fotos')))], 'restaurarFotos.*' => ['boolean']];
        foreach (config('pagina-laboratorio.textos') as $clave => $defecto) $reglas['textos.'.$clave] = ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u'];
        $reglas['textos.contacto_email'][] = 'email';
        foreach (config('pagina-laboratorio.fotos') as $clave => $defecto) {
            $reglas['alternativos.'.$clave] = ['required', 'string', 'max:180'];
            $reglas['fotos.'.$clave] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192', 'dimensions:max_width=4096,max_height=4096'];
        }
        $this->validate($reglas);
        try {
            $nuevas = [];
            foreach ($this->fotos as $clave => $foto) if ($foto) $nuevas[$clave] = $pagina->prepararFoto((string) file_get_contents($foto->getRealPath()));
            $pagina->guardar(array_map('trim', array_intersect_key($this->textos, config('pagina-laboratorio.textos'))), $this->alternativos, $nuevas, $this->restaurarFotos, $this->version, (string) auth()->id());
            $this->fotos = $this->restaurarFotos = [];
            $this->mount($pagina);
            session()->flash('laboratorio-guardado', 'Página publicada. Los textos y las fotografías ya están actualizados.');
        } catch (\Illuminate\Validation\ValidationException $error) { throw $error; }
        catch (\Throwable $error) { report($error); $this->addError('fotos', 'No se pudo publicar. El contenido anterior se conserva.'); }
    }

    public function render(): View
    {
        return view('livewire.administracion.configuracion-laboratorio', ['pagina' => app(PaginaLaboratorio::class)->leer()]);
    }
}
