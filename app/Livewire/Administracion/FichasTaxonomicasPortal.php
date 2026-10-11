<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;

#[Layout('layouts.app')]
#[Title('Fichas del explorador taxonómico')]
final class FichasTaxonomicasPortal extends Component
{
    use WithFileUploads, WithPagination;

    public string $busqueda = '';
    #[Locked] public string $taxonId = '';
    #[Locked] public string $version = '';
    public string $descripcion = '';
    public string $fotoAlt = '';
    public string $credito = '';
    public string $fuenteUrl = '';
    public bool $publicada = false;
    public bool $quitarFoto = false;
    public $foto;

    public function boot(): void { abort_unless(auth()->user()?->esCurador(), 403); }
    public function updatedBusqueda(): void { $this->resetPage(); }

    public function seleccionar(string $id): void
    {
        abort_unless(\Illuminate\Support\Str::isUuid($id) && DB::table('taxonomia.taxones')->where('id', $id)->exists(), 404);
        $ficha = app(FichasTaxonomicas::class)->buscar($id);
        $this->taxonId = $id; $this->version = (string) ($ficha->updated_at ?? '');
        $this->descripcion = $ficha->descripcion ?? ''; $this->fotoAlt = $ficha->foto_alt ?? '';
        $this->credito = $ficha->credito ?? ''; $this->fuenteUrl = $ficha->fuente_url ?? '';
        $this->publicada = (bool) ($ficha->publicada ?? false); $this->quitarFoto = false; $this->foto = null;
        $this->resetValidation(); session()->forget('ficha-guardada');
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        abort_unless($this->taxonId !== '' && Schema::hasTable(FichasTaxonomicas::TABLA), 409);
        $this->validate([
            'descripcion' => ['nullable', 'string', 'max:800'], 'fotoAlt' => ['nullable', 'string', 'max:180'],
            'credito' => ['nullable', 'string', 'max:500'], 'fuenteUrl' => ['nullable', 'url:https', 'max:1000'],
            'publicada' => ['boolean'], 'quitarFoto' => ['boolean'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        $fichas = app(FichasTaxonomicas::class);
        $actual = $fichas->buscar($this->taxonId);
        if ($this->publicada && ($this->foto || $actual?->foto_ruta && !$this->quitarFoto) && trim($this->fotoAlt) === '') {
            $this->addError('fotoAlt', 'Describe la fotografía para quienes utilizan lectores de pantalla.'); return;
        }
        if ($this->publicada && trim($this->descripcion) === '' && !$this->foto && (!$actual?->foto_ruta || $this->quitarFoto)) {
            $this->addError('descripcion', 'Agrega una descripción o una fotografía antes de publicar.'); return;
        }
        try {
            $foto = $this->foto ? $fichas->prepararFoto((string) file_get_contents($this->foto->getRealPath())) : null;
            $fichas->guardar($this->taxonId, [
                'descripcion' => trim($this->descripcion), 'foto_alt' => trim($this->fotoAlt), 'credito' => trim($this->credito),
                'fuente_url' => trim($this->fuenteUrl), 'publicada' => $this->publicada,
            ], $foto, $this->quitarFoto, (string) auth()->id(), $this->version);
            $this->seleccionar($this->taxonId);
            session()->flash('ficha-guardada', 'Ficha guardada. Se mostrará al volver a seleccionar el taxón en el explorador.');
        } catch (\Illuminate\Validation\ValidationException $error) { throw $error; }
        catch (\Throwable $error) { report($error); $this->addError('foto', 'No se pudo guardar la ficha. El contenido anterior se conserva; vuelve a intentarlo.'); }
    }

    public function render(): View
    {
        $lista = DB::table('taxonomia.taxones')->select('id', 'nombre_cientifico', 'rango')
            ->when(trim($this->busqueda) !== '', fn ($q) => $q->where('nombre_cientifico', 'ILIKE', '%'.addcslashes(mb_substr(trim($this->busqueda), 0, 120), '\\%_').'%'))
            ->orderBy('nombre_cientifico')->orderBy('id')->paginate(15);
        return view('livewire.administracion.fichas-taxonomicas-portal', [
            'taxones' => $lista, 'disponible' => Schema::hasTable(FichasTaxonomicas::TABLA),
            'taxon' => $this->taxonId ? DB::table('taxonomia.taxones')->where('id', $this->taxonId)->first(['nombre_cientifico', 'rango']) : null,
            'ficha' => $this->taxonId ? app(FichasTaxonomicas::class)->buscar($this->taxonId) : null,
        ]);
    }
}
