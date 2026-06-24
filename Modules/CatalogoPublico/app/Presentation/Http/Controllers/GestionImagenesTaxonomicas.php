<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonInput;
use Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto\SeleccionarImagenPorDefectoHandler;
use Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto\SeleccionarImagenPorDefectoInput;
use Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen\SubirImagenEspecimenHandler;
use Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen\SubirImagenEspecimenInput;

#[Layout('layouts.app', params: ['title' => 'Imágenes por nivel taxonómico'])]
final class GestionImagenesTaxonomicas extends Component
{
    use WithFileUploads;

    public string $buscar = '';

    public string $especieSeleccionada = '';

    public string $generoSeleccionado = '';

    public string $occurrenceSeleccionado = '';

    public string $nombreAutor = '';

    public string $apellidoAutor = '';

    public mixed $imagen = null;

    public string $mensaje = '';

    public string $error = '';

    /** Normaliza el nombre de especie igual que el árbol del portal (género + epíteto). */
    private const SPECIES_EXPR = "(CASE WHEN tx_species.nombre_cientifico LIKE tx_genus.nombre_cientifico || ' %' THEN tx_species.nombre_cientifico ELSE tx_genus.nombre_cientifico || ' ' || tx_species.nombre_cientifico END)";

    public function seleccionarEspecie(string $scientificName, string $genus): void
    {
        $this->especieSeleccionada = $scientificName;
        $this->generoSeleccionado = $genus;
        $this->occurrenceSeleccionado = (string) $this->baseDivulgados()
            ->whereRaw(self::SPECIES_EXPR.' = ?', [$scientificName])
            ->orderBy('te.occurrence_id')
            ->value('te.occurrence_id');
        $this->reset('mensaje', 'error');
    }

    public function subir(SubirImagenEspecimenHandler $handler): void
    {
        $this->reset('mensaje', 'error');

        if ($this->imagen === null) {
            $this->error = 'Selecciona una imagen y espera a que termine de cargar antes de subirla.';

            return;
        }

        try {
            $salida = $handler->handle(new SubirImagenEspecimenInput(
                occurrenceID: $this->occurrenceSeleccionado,
                nombreArchivo: $this->imagen->getClientOriginalName(),
                contenido: $this->imagen->get(),
                nombreAutor: $this->nombreAutor,
                apellidoAutor: $this->apellidoAutor,
            ));
            $this->reset('imagen', 'nombreAutor', 'apellidoAutor');
            $this->mensaje = "Imagen «{$salida->nombreArchivo}» subida y marcada con «{$salida->autor}».";
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function usarComoPortada(SeleccionarImagenPorDefectoHandler $handler, string $nivel, string $valor, string $imagenId): void
    {
        $this->reset('mensaje', 'error');

        try {
            $handler->handle(new SeleccionarImagenPorDefectoInput($nivel, $valor, $imagenId));
            $this->mensaje = 'Portada actualizada.';
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(ConsultarGaleriaTaxonHandler $galeria): View
    {
        $especies = $this->baseDivulgados()
            ->selectRaw(self::SPECIES_EXPR.' as scientific_name, tx_genus.nombre_cientifico as genus')
            ->when($this->buscar !== '', fn ($q) => $q->whereRaw('LOWER('.self::SPECIES_EXPR.') ILIKE ?', ['%'.mb_strtolower($this->buscar).'%']))
            ->groupByRaw(self::SPECIES_EXPR.', tx_genus.nombre_cientifico')
            ->orderByRaw(self::SPECIES_EXPR)
            ->get();

        $galeriaEspecie = $this->especieSeleccionada === ''
            ? []
            : $galeria->handle(new ConsultarGaleriaTaxonInput('species', $this->especieSeleccionada))->imagenes;

        $galeriaGenero = $this->generoSeleccionado === ''
            ? []
            : $galeria->handle(new ConsultarGaleriaTaxonInput('genus', $this->generoSeleccionado))->imagenes;

        $ocurrencias = $this->especieSeleccionada === ''
            ? collect()
            : $this->baseDivulgados()
                ->whereRaw(self::SPECIES_EXPR.' = ?', [$this->especieSeleccionada])
                ->orderBy('te.occurrence_id')
                ->pluck('te.occurrence_id');

        return view('catalogopublico::livewire.gestion-imagenes-taxonomicas', [
            'especies' => $especies,
            'galeriaEspecie' => $galeriaEspecie,
            'galeriaGenero' => $galeriaGenero,
            'ocurrencias' => $ocurrencias,
        ]);
    }

    /**
     * Base de especímenes divulgados con su jerarquía taxonómica completa
     * (mismo criterio que el árbol del portal).
     */
    private function baseDivulgados(): Builder
    {
        return DB::table('taxonomia.especimenes as te')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
            ->join('taxonomia.taxones as tx_species', 'tx_species.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 'tx_species.padre_id')
            ->leftJoin('taxonomia.taxones as tx_family', 'tx_family.id', '=', 'tx_genus.padre_id')
            ->leftJoin('taxonomia.taxones as tx_order', 'tx_order.id', '=', 'tx_family.padre_id')
            ->leftJoin('taxonomia.taxones as tx_class', 'tx_class.id', '=', 'tx_order.padre_id')
            ->leftJoin('taxonomia.taxones as tx_phylum', 'tx_phylum.id', '=', 'tx_class.padre_id')
            ->whereNotNull('tx_genus.id')
            ->whereNotNull('tx_family.id')
            ->whereNotNull('tx_order.id')
            ->whereNotNull('tx_class.id')
            ->whereNotNull('tx_phylum.id');
    }
}
