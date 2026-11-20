<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithInteractiveGrid;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon\ActualizarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon\ActualizarTaxonInput;

#[Layout('layouts.app')]
#[Title('Fichas del explorador taxonómico')]
final class FichasTaxonomicasPortal extends Component
{
    use WithInteractiveGrid;
    use WithFileUploads, WithPagination;

    public string $busqueda = '';
    #[Locked] public string $taxonId = '';
    #[Locked] public string $version = '';
    #[Locked] public string $nombreOriginal = '';
    public string $nombreCientifico = '';
    public string $descripcion = '';
    public string $fotoAlt = '';
    public string $credito = '';
    public string $fuenteUrl = '';
    public bool $publicada = false;
    public bool $quitarFoto = false;
    public $foto;
    public bool $editorFichaAbierto = false;

    public function boot(): void { abort_unless(auth()->user()?->esCurador(), 403); }
    public function updatedBusqueda(): void { $this->resetPage(); }

    public function seleccionar(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);
        $nombre = DB::table('taxonomia.taxones')->where('id', $id)->value('nombre_cientifico');
        abort_if($nombre === null, 404);
        $ficha = app(FichasTaxonomicas::class)->buscar($id);
        $this->taxonId = $id; $this->version = (string) ($ficha->updated_at ?? '');
        $this->nombreOriginal = $this->nombreCientifico = $nombre;
        $this->descripcion = $ficha->descripcion ?? ''; $this->fotoAlt = $ficha->foto_alt ?? '';
        $this->credito = $ficha->credito ?? ''; $this->fuenteUrl = $ficha->fuente_url ?? '';
        $this->publicada = (bool) ($ficha->publicada ?? false); $this->quitarFoto = false; $this->foto = null;
        $this->editorFichaAbierto = true;
        $this->resetValidation(); session()->forget('ficha-guardada');
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        abort_unless($this->taxonId !== '' && Schema::hasTable(FichasTaxonomicas::TABLA), 409);
        $nombre = trim($this->nombreCientifico);
        $renombrar = $this->nombreCientifico !== $this->nombreOriginal && $nombre !== $this->nombreOriginal;
        if ($renombrar) {
            abort_unless(auth()->user()?->esAdministrador(), 403);
            $this->nombreCientifico = $nombre;
            $this->validate(['nombreCientifico' => ['required', 'string', 'max:255']]);
        }
        $this->validate([
            'descripcion' => ['nullable', 'string', 'max:800'], 'fotoAlt' => ['nullable', 'string', 'max:180'],
            'credito' => ['nullable', 'string', 'max:500'], 'fuenteUrl' => ['nullable', 'url:https', 'max:1000'],
            'publicada' => ['boolean'], 'quitarFoto' => ['boolean'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192', 'dimensions:max_width=4096,max_height=4096'],
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
            DB::transaction(function () use ($fichas, $foto, $renombrar, $nombre): void {
                $fichas->guardar($this->taxonId, [
                    'descripcion' => trim($this->descripcion), 'foto_alt' => trim($this->fotoAlt), 'credito' => trim($this->credito),
                    'fuente_url' => trim($this->fuenteUrl), 'publicada' => $this->publicada,
                ], $foto, $this->quitarFoto, (string) auth()->id(), $this->version);

                if ($renombrar) {
                    $this->renombrarTaxon($nombre);
                }
            });
            $this->seleccionar($this->taxonId);
            $this->editorFichaAbierto = false;
            session()->flash('ficha-guardada', 'Transacción Efectuada');
        } catch (ValidationException $error) { throw $error; }
        catch (\Throwable $error) { report($error); $this->addError('foto', 'No se pudo guardar la ficha. El contenido anterior se conserva; vuelve a intentarlo.'); }
    }

    private function renombrarTaxon(string $nombre): void
    {
        // El guardado de la ficha bloquea esta fila hasta cerrar la transacción.
        $taxon = DB::table('taxonomia.taxones')->where('id', $this->taxonId)->first();
        if ($taxon->nombre_cientifico !== $this->nombreOriginal) {
            throw ValidationException::withMessages(['nombreCientifico' => 'El nombre cambió desde que abriste la ficha. Vuelve a abrirla para ver el nombre actual.']);
        }

        try {
            app(ActualizarTaxonHandler::class)->handle(new ActualizarTaxonInput(
                taxonId: $this->taxonId,
                nombreCientifico: $nombre,
                autor: $taxon->autor,
                anioDescripcion: $taxon->anio_descripcion,
                epitetoInfraespecifico: $taxon->epiteto_infraespecifico,
            ));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombreCientifico' => 'Ya existe un taxón con ese nombre científico y rango.']);
        }
    }

    public function render(): View
    {
        $disponible = Schema::hasTable(FichasTaxonomicas::TABLA);
        $lista = DB::table('taxonomia.taxones')->select('taxonomia.taxones.id', 'nombre_cientifico', 'rango')
            ->when(trim($this->busqueda) !== '', fn ($q) => $q->where('nombre_cientifico', 'ILIKE', '%'.addcslashes(mb_substr(trim($this->busqueda), 0, 120), '\\%_').'%'));
        if ($disponible) $lista->leftJoin(FichasTaxonomicas::TABLA.' as ficha', 'ficha.taxon_id', '=', 'taxonomia.taxones.id')
            ->addSelect('ficha.foto_ruta', 'ficha.publicada');
        $lista = $this->paginarGrilla($this->ordenarConsultaGrilla($lista, 'nombre_cientifico')->orderBy('id'));
        return view('livewire.administracion.fichas-taxonomicas-portal', [
            'taxones' => $lista, 'disponible' => $disponible,
            'taxon' => $this->taxonId ? DB::table('taxonomia.taxones')->where('id', $this->taxonId)->first(['nombre_cientifico', 'rango']) : null,
            'ficha' => $this->taxonId ? app(FichasTaxonomicas::class)->buscar($this->taxonId) : null,
        ]);
    }

    protected function gridColumns(): array
    {
        return ['nombre' => 'nombre_cientifico', 'rango' => 'rango'];
    }
}
