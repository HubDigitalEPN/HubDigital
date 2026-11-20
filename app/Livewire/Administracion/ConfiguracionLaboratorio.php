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
    use WithFileUploads, \App\Livewire\Concerns\WithFotosEditoriales, \App\Livewire\Concerns\WithTextosNavegacion;
    public array $textos = [], $alternativos = [], $fotos = [], $restaurarFotos = [];
    public array $bloques = [], $fotosBloques = [], $quitarFotosBloques = [];
    #[Locked] public string $version = '';
    #[Locked] public string $campoSeleccionado = '';
    public bool $textoAbierto = false;
    public bool $fotoAbierta = false, $bloqueAbierto = false;
    #[Locked] public string $fotoSeleccionada = '', $bloqueSeleccionado = '';
    #[Locked] public bool $bloqueNuevo = false;

    public function seleccionarElemento(string $id): void
    {
        if (preg_match('/^texto-bloque-([a-f0-9]{16})-(titulo|texto)$/D', $id, $partes)) {
            $indice = array_search($partes[1], array_column($this->bloques, 'id'), true);
            abort_if($indice === false, 422);
            $this->campoSeleccionado = 'bloques.'.$indice.'.'.$partes[2];
            $this->bloqueSeleccionado = $partes[1];
            $this->bloqueNuevo = false;
            $this->bloqueAbierto = true;
            return;
        }
        $clave = $id === 'texto-personal' ? 'personal_titulo' : substr($id, 6);
        abort_unless(str_starts_with($id, 'texto-') && array_key_exists($clave, config('pagina-laboratorio.textos')), 422);
        $this->campoSeleccionado = $clave;
        $this->textoAbierto = true;
    }

    public function seleccionarFoto(string $id): void
    {
        abort_unless(str_starts_with($id, 'foto-'), 422);
        $clave = substr($id, 5);
        if (str_starts_with($clave, 'bloque-')) {
            abort_unless(in_array(substr($clave, 7), array_column($this->bloques, 'id'), true), 422);
        } else {
            abort_unless(array_key_exists($clave, config('pagina-laboratorio.fotos')), 422);
        }
        $this->resetValidation();
        $this->fotoSeleccionada = $clave;
        $this->fotoAbierta = true;
    }

    public function crearBloqueVisual(): void
    {
        if (count($this->bloques) >= 30) { $this->addError('bloques', 'Puedes publicar hasta 30 bloques adicionales.'); return; }
        $this->agregarBloque();
        $indice = array_key_last($this->bloques);
        $id = $this->bloques[$indice]['id'];
        $this->bloqueSeleccionado = $id;
        $this->bloqueNuevo = true;
        $this->bloqueAbierto = true;
    }

    public function cancelarBloqueVisual(): void
    {
        if ($this->bloqueNuevo) {
            $this->quitarBloque($this->bloqueSeleccionado);
        }
        $this->bloqueAbierto = $this->bloqueNuevo = false;
    }

    public function updatedBloqueAbierto(bool $abierto): void
    {
        if (!$abierto) $this->cancelarBloqueVisual();
    }

    public function quitarBloqueVisual(): void
    {
        $id = $this->bloqueSeleccionado;
        $this->quitarBloque($id);
        $this->bloqueAbierto = $this->bloqueNuevo = false;
        $this->dispatch('laboratorio-bloque-retirado', id: $id);
    }

    public function boot(): void
    {
        abort_unless(in_array(auth()->user()?->rolActivo(), [RolUsuario::ADMIN, RolUsuario::CURADOR], true), 403);
    }

    public function mount(PaginaLaboratorio $pagina): void
    {
        $datos = $pagina->leer(); $this->textos = $datos['textos']; $this->version = $datos['version'];
        $this->bloques = array_map(fn ($bloque) => array_intersect_key($bloque, array_flip(['id', 'titulo', 'texto', 'alt'])), $datos['bloques']);
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

    public function guardar(PaginaLaboratorio $pagina): void
    {
        $reglas = ['textos' => ['required', 'array'], 'alternativos' => ['required', 'array'],
            'fotos' => ['array:'.implode(',', array_keys(config('pagina-laboratorio.fotos')))], 'restaurarFotos.*' => ['boolean']];
        $largos = ['principal', 'personal_descripcion', 'adrian_bio', 'adrian_especialidad', 'vladimir_bio', 'david_bio', 'colaboracion_texto'];
        foreach (config('pagina-laboratorio.textos') as $clave => $defecto) $reglas['textos.'.$clave] = ['required', 'string', 'max:'.(in_array($clave, $largos, true) || str_ends_with($clave, '_bio') ? '20000' : '5000'), 'not_regex:/^\s*$/u'];
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
            $pagina->guardar(array_map('trim', array_intersect_key($this->textos, config('pagina-laboratorio.textos'))), $this->alternativos, $nuevas, $restaurar, $this->version, (string) auth()->id(), $this->bloques);
            $this->fotos = $this->restaurarFotos = [];
            $this->fotosBloques = $this->quitarFotosBloques = [];
            $this->mount($pagina);
            $this->textoAbierto = false;
            $this->fotoAbierta = $this->bloqueAbierto = $this->bloqueNuevo = false;
            $this->dispatch('laboratorio-publicado');
            session()->flash('laboratorio-guardado', 'Transacción Efectuada');
        } catch (\Illuminate\Validation\ValidationException $error) { throw $error; }
        catch (\Throwable $error) { report($error); $this->addError('fotos', 'No se pudo publicar. El contenido anterior se conserva.'); }
    }

    public function publicarTextoVisual(string $campo, string $contenido, ?string $complemento, PaginaLaboratorio $pagina): array
    {
        abort_unless(array_key_exists($campo, config('pagina-laboratorio.textos')), 422);
        $anteriores = $this->textos;
        $this->textos[$campo] = $contenido;
        $campoComplementario = match ($campo) {
            'personal_titulo' => 'personal_destacado',
            'adrian_bio' => 'adrian_especialidad',
            'contacto_accion' => 'contacto_email',
            default => null,
        };
        if ($campoComplementario !== null && $complemento !== null) {
            $this->textos[$campoComplementario] = $complemento;
        }

        $this->resetValidation();
        try {
            $this->guardar($pagina);
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->setErrorBag($error->validator->errors());
        }
        if ($this->getErrorBag()->isEmpty()) {
            return ['guardado' => true];
        }

        $this->textos = $anteriores;
        return ['error' => $this->getErrorBag()->first()];
    }

    public function render(): View
    {
        $indiceFoto = str_starts_with($this->fotoSeleccionada, 'bloque-') ? array_search(substr($this->fotoSeleccionada, 7), array_column($this->bloques, 'id'), true) : false;
        return view('livewire.administracion.configuracion-laboratorio', [
            'modeloSeleccionado' => str_starts_with($this->campoSeleccionado, 'bloques.') ? $this->campoSeleccionado : 'textos.'.$this->campoSeleccionado,
            'indiceBloque' => array_search($this->bloqueSeleccionado, array_column($this->bloques, 'id'), true),
            'fotoBloque' => $indiceFoto !== false,
            'modeloFoto' => $indiceFoto !== false ? 'fotosBloques.'.substr($this->fotoSeleccionada, 7) : 'fotos.'.$this->fotoSeleccionada,
            'modeloAlternativo' => $indiceFoto !== false ? 'bloques.'.$indiceFoto.'.alt' : 'alternativos.'.$this->fotoSeleccionada,
            'modeloRestaurarFoto' => $indiceFoto !== false ? 'quitarFotosBloques.'.substr($this->fotoSeleccionada, 7) : 'restaurarFotos.'.$this->fotoSeleccionada,
        ]);
    }
}
