<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoHandler;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoInput;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoOutput;

#[Layout('layouts.portal', params: ['title' => 'Catálogo taxonómico · Departamento de Biología — EPN'])]
final class ArbolTaxonomicoDivulgacion extends Component
{
    private const array NIVEL_SIGUIENTE = [
        '' => 'phylum',
        'phylum' => 'class',
        'class' => 'order',
        'order' => 'family',
        'family' => 'genus',
        'genus' => 'species',
    ];

    private const array NIVEL_PADRE = [
        'phylum' => '',
        'class' => 'phylum',
        'order' => 'class',
        'family' => 'order',
        'genus' => 'family',
        'species' => 'genus',
    ];

    private const array NIVEL_ETIQUETA = [
        '' => 'Catálogo',
        'phylum' => 'Filo',
        'class' => 'Clase',
        'order' => 'Orden',
        'family' => 'Familia',
        'genus' => 'Género',
        'species' => 'Especie',
    ];

    private const array DESCENDANT_LABELS = [
        'class' => 'clases',
        'order' => 'órdenes',
        'family' => 'familias',
        'genus' => 'géneros',
        'species' => 'especies',
    ];

    /** Etiqueta plural usada en el encabezado del modo explorar */
    private const array NIVEL_PLURAL = [
        'phylum' => 'filos',
        'class' => 'clases',
        'order' => 'órdenes',
        'family' => 'familias',
        'genus' => 'géneros',
        'species' => 'especies',
    ];

    #[Url(as: 'nivel')]
    public string $nivel = '';

    #[Url(as: 'taxon')]
    public string $taxon = '';

    /** Cuando está definido, muestra todos los taxones de ese nivel (modo explorar) */
    #[Url(as: 'explorar')]
    public string $explorar = '';

    public function navegar(string $nivel, string $taxon): void
    {
        $this->nivel = $nivel;
        $this->taxon = $taxon;
        $this->explorar = '';
    }

    public function explorarNivel(string $nivel): void
    {
        $this->explorar = $nivel;
        $this->nivel = '';
        $this->taxon = '';
    }

    public function volverAlArbol(): void
    {
        $this->explorar = '';
    }

    public function render(ConstruirArbolTaxonomicoHandler $handler, ProveedorEspecimenesPort $proveedor): View
    {
        $output = ($handler)(new ConstruirArbolTaxonomicoInput);

        $totalGlobal = (int) array_sum(array_map('count', $output->especimenesPorEspecie));
        $conteos = $this->calcularConteos($output);
        $ruta = $this->resolverRuta($output);
        $hijos = $this->resolverHijos($output);
        $especiesActuales = $this->nivel === 'genus' ? $this->resolverEspecies($output) : [];
        $hermanos = $this->nivel !== '' ? $this->resolverHermanos($output) : [];
        $especimenes = $this->nivel === 'species'
            ? $this->cargarDetallesEspecimenes($output->especimenesPorEspecie[$this->taxon] ?? [], $proveedor)
            : [];

        $descendientes = $this->calcularDescendientes($output);

        return view('catalogopublico::livewire.arbol-taxonomico-divulgacion', [
            'ruta' => $ruta,
            'hijos' => $hijos,
            'especiesActuales' => $especiesActuales,
            'hermanos' => $hermanos,
            'especimenes' => $especimenes,
            'conteos' => $conteos,
            'descendientes' => $descendientes,
            'taxonesExplorados' => $this->explorar !== ''
                ? $this->resolverTaxonesParaExplorar($output, $this->explorar)
                : [],
            'totalGlobal' => $totalGlobal,
            'nivelActual' => $this->nivel,
            'taxonActual' => $this->taxon,
            'nivelExplorar' => $this->explorar,
            'nivelHijo' => self::NIVEL_SIGUIENTE[$this->nivel] ?? '',
            'etiquetas' => self::NIVEL_ETIQUETA,
            'etiquetasDescendientes' => self::DESCENDANT_LABELS,
            'nivelesNavegacion' => self::NIVEL_ETIQUETA,
            'nivelesPluralNavegacion' => self::NIVEL_PLURAL,
        ]);
    }

    /** @return list<array{nivel: string, taxon: string, etiqueta: string}> */
    private function resolverRuta(ConstruirArbolTaxonomicoOutput $output): array
    {
        if ($this->nivel === '' || $this->taxon === '') {
            return [];
        }

        $ruta = [];
        $nivelCursor = $this->nivel;
        $taxonCursor = $this->taxon;

        while ($nivelCursor !== '' && $taxonCursor !== '') {
            $ruta[] = [
                'nivel' => $nivelCursor,
                'taxon' => $taxonCursor,
                'etiqueta' => self::NIVEL_ETIQUETA[$nivelCursor] ?? $nivelCursor,
            ];

            if ($nivelCursor === 'species') {
                $nodoEspecie = collect($output->especies)
                    ->first(fn ($e) => $e['especie'] === $taxonCursor);

                if ($nodoEspecie) {
                    $nivelCursor = 'genus';
                    $taxonCursor = $nodoEspecie['padre'];
                } else {
                    break;
                }

                continue;
            }

            $nodo = collect($output->nodosJerarquicos)
                ->first(fn ($n) => $n['nivel'] === $nivelCursor && $n['taxon'] === $taxonCursor);

            if (! $nodo || $nodo['padre'] === 'root') {
                break;
            }

            $nivelCursor = self::NIVEL_PADRE[$nivelCursor] ?? '';
            $taxonCursor = $nodo['padre'];
        }

        return array_reverse($ruta);
    }

    /** @return list<array{nivel: string, taxon: string, padre: string}> */
    private function resolverHijos(ConstruirArbolTaxonomicoOutput $output): array
    {
        $nivelHijo = self::NIVEL_SIGUIENTE[$this->nivel] ?? null;

        if ($nivelHijo === null || $nivelHijo === 'species') {
            return [];
        }

        $padre = $this->taxon === '' ? 'root' : $this->taxon;

        return collect($output->nodosJerarquicos)
            ->filter(fn ($n) => $n['nivel'] === $nivelHijo && $n['padre'] === $padre)
            ->values()
            ->all();
    }

    /** @return list<array{especie: string, genus: string, specificEpithet: string, padre: string}> */
    private function resolverEspecies(ConstruirArbolTaxonomicoOutput $output): array
    {
        return collect($output->especies)
            ->filter(fn ($e) => $e['padre'] === $this->taxon)
            ->values()
            ->all();
    }

    /**
     * Hermanos normalizados: siempre [{nivel, taxon, esEspecie}].
     *
     * @return list<array{nivel: string, taxon: string, esEspecie: bool}>
     */
    private function resolverHermanos(ConstruirArbolTaxonomicoOutput $output): array
    {
        if ($this->nivel === 'species') {
            $nodoEspecie = collect($output->especies)
                ->first(fn ($e) => $e['especie'] === $this->taxon);

            if (! $nodoEspecie) {
                return [];
            }

            return collect($output->especies)
                ->filter(fn ($e) => $e['padre'] === $nodoEspecie['padre'] && $e['especie'] !== $this->taxon)
                ->map(fn ($e) => ['nivel' => 'species', 'taxon' => $e['especie'], 'esEspecie' => true])
                ->values()
                ->all();
        }

        $nodo = collect($output->nodosJerarquicos)
            ->first(fn ($n) => $n['nivel'] === $this->nivel && $n['taxon'] === $this->taxon);

        if (! $nodo) {
            return [];
        }

        return collect($output->nodosJerarquicos)
            ->filter(fn ($n) => $n['nivel'] === $this->nivel && $n['padre'] === $nodo['padre'] && $n['taxon'] !== $this->taxon)
            ->map(fn ($n) => ['nivel' => $n['nivel'], 'taxon' => $n['taxon'], 'esEspecie' => false])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $occurrenceIDs
     * @return list<object>
     */
    private function cargarDetallesEspecimenes(array $occurrenceIDs, ProveedorEspecimenesPort $proveedor): array
    {
        if ($occurrenceIDs === []) {
            return [];
        }

        return array_map(
            fn (DatosEspecimenProveedor $dto): object => (object) [
                'occurrence_id' => $dto->occurrenceId,
                'scientific_name' => $dto->scientificName,
                'individual_count' => $dto->individualCount,
                'type_status' => $dto->typeStatus,
                'type_notes' => $dto->typeNotes,
                'specimen_notes' => $dto->specimenNotes,
                'sampling_protocol' => $dto->samplingProtocol,
                'recorded_by' => $dto->recordedBy,
                'occurrence_status' => $dto->occurrenceStatus,
                'country' => $dto->country,
                'locality_name' => $dto->localityName,
                'decimal_latitude' => $dto->decimalLatitude,
                'decimal_longitude' => $dto->decimalLongitude,
            ],
            $proveedor->buscarPorOccurrenceIds($occurrenceIDs)
        );
    }

    /**
     * Todos los taxones de un nivel dado, ordenados alfabéticamente.
     * Normaliza filos/clases/órdenes/familias/géneros desde nodosJerarquicos
     * y especies desde la lista de especies.
     *
     * @return list<array{nivel: string, taxon: string, padre: string}>
     */
    private function resolverTaxonesParaExplorar(ConstruirArbolTaxonomicoOutput $output, string $nivel): array
    {
        if ($nivel === 'species') {
            return collect($output->especies)
                ->map(fn ($e) => ['nivel' => 'species', 'taxon' => $e['especie'], 'padre' => $e['padre']])
                ->sortBy('taxon')
                ->values()
                ->all();
        }

        return collect($output->nodosJerarquicos)
            ->filter(fn ($n) => $n['nivel'] === $nivel)
            ->sortBy('taxon')
            ->values()
            ->all();
    }

    /**
     * Cantidad de taxones descendientes por nivel, para cada nodo del árbol.
     * Permite mostrar en cada tarjeta cuántas clases, órdenes, familias, géneros y especies contiene.
     *
     * @return array<string, array<string, int>> clave: "nivel:taxon" → [nivel_desc => cantidad]
     */
    private function calcularDescendientes(ConstruirArbolTaxonomicoOutput $output): array
    {
        $niveles = ['phylum', 'class', 'order', 'family', 'genus', 'species'];
        $ordenNivel = array_flip($niveles);

        $porNivelYPadre = [];
        foreach ($output->nodosJerarquicos as $nodo) {
            $porNivelYPadre[$nodo['nivel']][$nodo['padre']][] = $nodo['taxon'];
        }

        $especiesPorGenus = [];
        foreach ($output->especies as $especie) {
            $especiesPorGenus[$especie['padre']][] = $especie['especie'];
        }

        $resultado = [];

        foreach ($output->nodosJerarquicos as $nodo) {
            $clave = $nodo['nivel'].':'.$nodo['taxon'];
            $nivelIdx = $ordenNivel[$nodo['nivel']];
            $descendantesPorNivel = [];
            $taxonsActuales = [$nodo['taxon']];

            for ($i = $nivelIdx + 1; $i < count($niveles); $i++) {
                $nivelDesc = $niveles[$i];
                $siguientes = [];

                foreach ($taxonsActuales as $padre) {
                    $hijos = $nivelDesc === 'species'
                        ? ($especiesPorGenus[$padre] ?? [])
                        : ($porNivelYPadre[$nivelDesc][$padre] ?? []);
                    $siguientes = array_merge($siguientes, $hijos);
                }

                if ($siguientes !== []) {
                    $descendantesPorNivel[$nivelDesc] = count($siguientes);
                }

                $taxonsActuales = $siguientes;
            }

            $resultado[$clave] = $descendantesPorNivel;
        }

        return $resultado;
    }

    /**
     * Total de especímenes por nodo, propagado desde especie hacia arriba.
     *
     * @return array<string, int> clave: "nivel:taxon"
     */
    private function calcularConteos(ConstruirArbolTaxonomicoOutput $output): array
    {
        $conteos = [];

        foreach ($output->especimenesPorEspecie as $especie => $ids) {
            $conteos['species:'.$especie] = count($ids);
        }

        foreach ($output->especies as $nodo) {
            $genero = $nodo['padre'];
            $conteos['genus:'.$genero] = ($conteos['genus:'.$genero] ?? 0)
                + ($conteos['species:'.$nodo['especie']] ?? 0);
        }

        $ascenso = ['genus' => 'family', 'family' => 'order', 'order' => 'class', 'class' => 'phylum'];

        foreach ($ascenso as $nivelHijo => $nivelPadre) {
            foreach ($output->nodosJerarquicos as $nodo) {
                if ($nodo['nivel'] !== $nivelHijo) {
                    continue;
                }

                $conteos[$nivelPadre.':'.$nodo['padre']] = ($conteos[$nivelPadre.':'.$nodo['padre']] ?? 0)
                    + ($conteos[$nivelHijo.':'.$nodo['taxon']] ?? 0);
            }
        }

        return $conteos;
    }
}
