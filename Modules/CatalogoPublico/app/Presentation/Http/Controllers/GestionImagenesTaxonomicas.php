<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonInput;
use Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto\SeleccionarImagenPorDefectoHandler;
use Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto\SeleccionarImagenPorDefectoInput;
use Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen\SubirImagenEspecimenHandler;
use Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen\SubirImagenEspecimenInput;
use Modules\CatalogoPublico\Domain\ValueObjects\AtribucionImagen;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\ImagenTaxonomicaEloquentModel;

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

    public string $photographer = '';
    public string $license = '';
    public string $licenseHolder = '';
    public string $licenseInstitution = '';
    public string $contact = '';
    public string $viewLabel = '';

    #[Locked]
    public ?string $imagenEditada = null;

    public string $vista = 'L';

    public string $sufijoPersonalizado = '';

    public mixed $imagen = null;

    /** @var array<string, string> Código de vista => etiqueta legible. */
    public const VISTAS_ESTANDAR = [
        'L' => 'Lateral',
        'D' => 'Dorsal',
        'F' => 'Frontal',
        'G' => 'Genital',
        'p' => 'Perfil',
        'otra' => 'Otra…',
    ];

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
        $this->cancelarEdicion();
    }

    public function subir(SubirImagenEspecimenHandler $handler): void
    {
        $this->reset('mensaje', 'error');

        if ($this->imagen === null) {
            $this->error = 'Selecciona una imagen y espera a que termine de cargar antes de subirla.';

            return;
        }

        $vistaSufijo = $this->resolverVistaSufijo();

        if ($vistaSufijo === null) {
            $this->error = 'Indica un sufijo para la vista personalizada (una o dos letras).';

            return;
        }

        $this->validarAtribuciones();
        $this->validate(['imagen' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,avif', 'max:25600']]);
        abort_unless($this->baseDivulgados()->where('te.occurrence_id', $this->occurrenceSeleccionado)->exists(), 422);

        try {
            $salida = $handler->handle(new SubirImagenEspecimenInput(
                occurrenceID: $this->occurrenceSeleccionado,
                nombreArchivo: $this->imagen->getClientOriginalName(),
                contenido: $this->imagen->get(),
                nombreAutor: $this->nombreAutor,
                apellidoAutor: $this->apellidoAutor,
                vistaSufijo: $vistaSufijo,
                atribucion: $this->atribucion(AtribucionImagen::vistaDesdeSufijo($vistaSufijo)),
            ));
            $this->reset('imagen', 'nombreAutor', 'apellidoAutor', 'sufijoPersonalizado');
            $this->cancelarEdicion();
            $this->vista = 'L';
            $this->mensaje = "Imagen «{$salida->nombreArchivo}» subida con sus atribuciones, sin añadir texto a la fotografía.";
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    private function validarAtribuciones(): void
    {
        $reglas = [];
        foreach (['photographer', 'license', 'licenseHolder', 'licenseInstitution', 'contact'] as $campo) {
            $reglas[$campo] = ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'];
        }
        $reglas['viewLabel'] = ['nullable', 'string', 'max:80', 'not_regex:/[\x00-\x1F\x7F]/u'];
        $this->validate($reglas);
    }

    private function atribucion(string $vista = ''): AtribucionImagen
    {
        return new AtribucionImagen(trim($this->photographer), trim($this->license), trim($this->licenseHolder),
            trim($this->licenseInstitution), trim($this->contact), trim($this->viewLabel) ?: $vista);
    }

    public function editarAtribuciones(string $id): void
    {
        $foto = $this->fotoEditable($id);
        $this->imagenEditada = $id;
        $this->photographer = $foto->photographer ?? $foto->autor_nombre_completo;
        $this->license = $foto->license ?? '';
        $this->licenseHolder = $foto->license_holder ?? '';
        $this->licenseInstitution = $foto->license_institution ?? '';
        $this->contact = $foto->contact ?? '';
        $this->viewLabel = $foto->view ?? AtribucionImagen::vistaDesdeNombre($foto->nombre_original);
        $this->resetValidation();
        $this->reset('mensaje', 'error');
    }

    public function guardarAtribuciones(): void
    {
        abort_if($this->imagenEditada === null, 422);
        $foto = $this->fotoEditable($this->imagenEditada);
        $this->validarAtribuciones();
        // No reescribe autor_nombre/apellido históricos, archivo, huella ni portada.
        $foto->update($this->atribucion()->toArray());
        $this->cancelarEdicion();
        $this->mensaje = 'Atribuciones actualizadas.';
    }

    public function cancelarEdicion(): void
    {
        $this->reset('imagenEditada', 'photographer', 'license', 'licenseHolder', 'licenseInstitution', 'contact', 'viewLabel');
        $this->resetValidation();
    }

    private function fotoEditable(string $id): ImagenTaxonomicaEloquentModel
    {
        abort_unless(\Illuminate\Support\Str::isUuid($id), 404);
        $foto = ImagenTaxonomicaEloquentModel::query()->findOrFail($id);
        abort_unless($this->baseDivulgados()->where('te.occurrence_id', $foto->occurrence_id)->exists(), 404);
        return $foto;
    }

    private function resolverVistaSufijo(): ?string
    {
        if ($this->vista === 'otra') {
            $limpio = preg_replace('/[^A-Za-z]/', '', $this->sufijoPersonalizado) ?? '';

            return $limpio === '' ? null : $limpio;
        }

        return $this->vista;
    }

    public function usarComoPortada(SeleccionarImagenPorDefectoHandler $handler, string $nivel, string $imagenId): void
    {
        $this->reset('mensaje', 'error');

        $valor = $nivel === 'species' ? $this->especieSeleccionada : $this->generoSeleccionado;

        if ($valor === '') {
            $this->error = 'Selecciona una especie antes de elegir la portada.';

            return;
        }

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
     * (mismo criterio que el árbol del portal): se restringe a los taxón-especie
     * cuya cadena de ancestros contiene los seis rangos canónicos
     * (phylum → clase → orden → familia → género → especie), verificados por el
     * campo `rango` de `taxonomia.taxones` — no por el orden de `padre_id`.
     */
    private function baseDivulgados(): Builder
    {
        return DB::table('taxonomia.especimenes as te')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
            ->join('taxonomia.taxones as tx_species', 'tx_species.id', '=', 'te.taxon_id')
            ->join('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 'tx_species.padre_id')
            ->where('tx_species.rango', 'especie')
            ->where('tx_genus.rango', 'genero')
            ->whereIn('te.taxon_id', $this->taxonesConCadenaCanonicaCompleta());
    }

    /**
     * UUIDs de taxón-especie cuya cadena de ancestros contiene los seis rangos
     * canónicos completos (phylum, clase, orden, familia, genero, especie),
     * ignorando rangos intermedios como suborden, subfamilia o tribu.
     *
     * @return list<string>
     */
    private function taxonesConCadenaCanonicaCompleta(): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE cadena AS (
                SELECT tx.id AS raiz, tx.rango, tx.padre_id, 0 AS profundidad
                FROM taxonomia.taxones tx
                WHERE tx.rango = 'especie'
                UNION ALL
                SELECT c.raiz, p.rango, p.padre_id, c.profundidad + 1
                FROM cadena c
                JOIN taxonomia.taxones p ON p.id = c.padre_id
                WHERE c.profundidad < 20
            )
            SELECT raiz::text AS raiz
            FROM cadena
            WHERE rango IN ('phylum', 'clase', 'orden', 'familia', 'genero', 'especie')
            GROUP BY raiz
            HAVING COUNT(DISTINCT rango) = 6
        SQL;

        return array_column(DB::select($sql), 'raiz');
    }
}
