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
    public array $bloques = [], $fotosBloques = [], $quitarFotosBloques = [];
    public array $posiciones = [];
    #[Locked] public string $version = '';

    public function boot(): void
    {
        abort_unless(in_array(auth()->user()?->rolActivo(), [RolUsuario::ADMIN, RolUsuario::CURADOR], true), 403);
    }

    public function mount(PaginaLaboratorio $pagina): void
    {
        $datos = $pagina->leer(); $this->textos = $datos['textos']; $this->version = $datos['version'];
        $this->bloques = array_map(fn ($bloque) => array_intersect_key($bloque, array_flip(['id', 'titulo', 'texto', 'alt'])), $datos['bloques']);
        $this->posiciones = $datos['posiciones'];
        $this->alternativos = [];
        foreach ($datos['fotos'] as $clave => $foto) $this->alternativos[$clave] = $foto['alt'];
    }

    public function agregarBloque(): void
    {
        if (count($this->bloques) >= 30) { $this->addError('bloques', 'Puedes publicar hasta 30 bloques adicionales.'); return; }
        $this->bloques[] = ['id' => bin2hex(random_bytes(8)), 'titulo' => '', 'texto' => '', 'alt' => 'Fotografía del laboratorio', 'foto' => ['ruta' => null, 'sha256' => null]];
    }

    public function quitarBloque(string $id): void
    {
        $this->bloques = array_values(array_filter($this->bloques, fn ($bloque) => $bloque['id'] !== $id));
        unset($this->fotosBloques[$id], $this->quitarFotosBloques[$id]);
    }

    public function moverBloque(string $id, int $direccion): void
    {
        if (!in_array($direccion, [-1, 1], true)) return;
        $origen = array_search($id, array_column($this->bloques, 'id'), true);
        if ($origen === false || !isset($this->bloques[$origen + $direccion])) return;
        [$this->bloques[$origen], $this->bloques[$origen + $direccion]] = [$this->bloques[$origen + $direccion], $this->bloques[$origen]];
    }

    public function guardar(PaginaLaboratorio $pagina): void
    {
        $reglas = ['textos' => ['required', 'array'], 'alternativos' => ['required', 'array'],
            'fotos' => ['array:'.implode(',', array_keys(config('pagina-laboratorio.fotos')))], 'restaurarFotos.*' => ['boolean']];
        $largos = ['principal', 'personal_descripcion', 'adrian_bio', 'adrian_especialidad', 'vladimir_bio', 'david_bio', 'colaboracion_texto'];
        foreach (config('pagina-laboratorio.textos') as $clave => $defecto) $reglas['textos.'.$clave] = ['required', 'string', 'max:'.(in_array($clave, $largos, true) ? '20000' : '5000'), 'not_regex:/^\s*$/u'];
        $reglas += [
            'bloques' => ['array', 'max:30'], 'bloques.*.id' => ['required', 'regex:/\A[a-f0-9]{16}\z/', 'distinct'],
            'bloques.*.titulo' => ['nullable', 'string', 'max:180'], 'bloques.*.texto' => ['nullable', 'string', 'max:20000'],
            'bloques.*.alt' => ['required', 'string', 'max:180'], 'fotosBloques' => ['array'],
            'fotosBloques.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192', 'dimensions:max_width=4096,max_height=4096'],
            'quitarFotosBloques.*' => ['boolean'],
        ];
        $reglas['textos.contacto_email'][] = 'email';
        foreach (config('pagina-laboratorio.fotos') as $clave => $defecto) {
            $reglas['alternativos.'.$clave] = ['required', 'string', 'max:180'];
            $reglas['fotos.'.$clave] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192', 'dimensions:max_width=4096,max_height=4096'];
        }
        $this->validate($reglas);
        $publicados = collect($pagina->leer()['bloques'])->keyBy('id');
        foreach ($this->bloques as $indice => $bloque) {
            if (trim($bloque['titulo'] ?? '') === '' && trim($bloque['texto'] ?? '') === '' && empty($this->fotosBloques[$bloque['id']])
                && (!empty($this->quitarFotosBloques[$bloque['id']]) || empty($publicados->get($bloque['id'])['foto']['ruta']))) {
                $this->addError('bloques.'.$indice.'.texto', 'Añade texto o una fotografía, o quita este bloque vacío.'); return;
            }
        }
        try {
            $nuevas = [];
            foreach ($this->fotos as $clave => $foto) if ($foto) $nuevas[$clave] = $pagina->prepararFoto((string) file_get_contents($foto->getRealPath()));
            $restaurar = $this->restaurarFotos;
            foreach ($this->bloques as $bloque) {
                $id = $bloque['id'];
                if (!empty($this->fotosBloques[$id])) $nuevas['bloque-'.$id] = $pagina->prepararFoto((string) file_get_contents($this->fotosBloques[$id]->getRealPath()));
                $restaurar['bloque-'.$id] = $this->quitarFotosBloques[$id] ?? false;
            }
            $pagina->guardar(array_map('trim', array_intersect_key($this->textos, config('pagina-laboratorio.textos'))), $this->alternativos, $nuevas, $restaurar, $this->version, (string) auth()->id(), $this->bloques, $this->posiciones);
            $this->fotos = $this->restaurarFotos = [];
            $this->fotosBloques = $this->quitarFotosBloques = [];
            $this->mount($pagina);
            $this->dispatch('laboratorio-publicado');
            session()->flash('laboratorio-guardado', 'Página publicada. Los textos y las fotografías ya están actualizados.');
        } catch (\Illuminate\Validation\ValidationException $error) { throw $error; }
        catch (\Throwable $error) { report($error); $this->addError('fotos', 'No se pudo publicar. El contenido anterior se conserva.'); }
    }

    public function render(): View
    {
        return view('livewire.administracion.configuracion-laboratorio', ['pagina' => app(PaginaLaboratorio::class)->leer()]);
    }
}
