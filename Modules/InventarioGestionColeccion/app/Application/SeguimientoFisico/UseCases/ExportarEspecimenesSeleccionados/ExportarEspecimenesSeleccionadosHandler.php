<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarEspecimenesSeleccionados;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;

/**
 * Arma una tabla (columnas Darwin Core) con SOLO los especímenes seleccionados,
 * lista para volcarse a un Excel formateado. Resuelve la jerarquía taxonómica
 * (kingdom…genus + scientificName) igual que el exportador a Darwin Core Archive,
 * para que ambas salidas sean consistentes.
 */
final class ExportarEspecimenesSeleccionadosHandler
{
    /** @var string[] */
    private const COLUMNAS = [
        'occurrenceID', 'catalogNumber', 'oldCode',
        'scientificName', 'taxonRank',
        'kingdom', 'phylum', 'class', 'order', 'family', 'genus',
        'recordedBy', 'eventDate', 'eventDateEnd',
        'country', 'stateProvince', 'municipality', 'locality',
        'decimalLatitude', 'decimalLongitude', 'geodeticDatum',
        'individualCount', 'sex', 'lifeStage', 'caste',
        'preparations', 'disposition', 'occurrenceStatus', 'typeStatus',
        'habitat', 'biome', 'occurrenceRemarks', 'taxonomicNotes',
    ];

    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ExportarEspecimenesSeleccionadosInput $input): ExportarEspecimenesSeleccionadosOutput
    {
        $especimenes = $this->especimenRepo->buscarPorIds(array_values(array_unique($input->ids)));

        $taxones = $this->cargarTaxones($especimenes);
        $jerarquias = $this->resolverJerarquias($taxones);

        $filas = [];
        foreach ($especimenes as $e) {
            $taxonId = $e->taxonId();
            $taxon = $taxonId !== null ? ($taxones[$taxonId] ?? null) : null;
            $jerarquia = $taxonId !== null ? ($jerarquias[$taxonId] ?? []) : [];

            $filas[] = [
                'occurrenceID' => 'MEPN:INV:'.((string) $e->id()),
                'catalogNumber' => (string) ($e->catalogNumber() ?? $e->codigoCatalogo()),
                'oldCode' => (string) ($e->oldCode() ?? ''),
                'scientificName' => (string) ($taxon?->nombreCientifico() ?? $e->taxonVerbatim() ?? ''),
                'taxonRank' => (string) ($taxon?->rango()->value ?? ''),
                'kingdom' => (string) ($jerarquia['kingdom'] ?? ''),
                'phylum' => (string) ($jerarquia['phylum'] ?? ''),
                'class' => (string) ($jerarquia['class'] ?? ''),
                'order' => (string) ($jerarquia['order'] ?? ''),
                'family' => (string) ($jerarquia['family'] ?? ''),
                'genus' => (string) ($jerarquia['genus'] ?? ''),
                'recordedBy' => (string) $e->colector(),
                'eventDate' => (string) $e->fechaColecta(),
                'eventDateEnd' => (string) ($e->fechaColectaFin() ?? ''),
                'country' => (string) ($e->country() ?? ''),
                'stateProvince' => (string) ($e->stateProvince() ?? ''),
                'municipality' => (string) ($e->municipality() ?? ''),
                'locality' => (string) ($e->localityName() ?? $e->localidad()),
                'decimalLatitude' => $e->decimalLatitude() !== null ? (string) $e->decimalLatitude() : '',
                'decimalLongitude' => $e->decimalLongitude() !== null ? (string) $e->decimalLongitude() : '',
                'geodeticDatum' => (string) ($e->geodeticDatum() ?? ''),
                'individualCount' => $e->individualCount() !== null ? (string) $e->individualCount() : '',
                'sex' => (string) ($e->sex() ?? ''),
                'lifeStage' => (string) ($e->lifeStage() ?? ''),
                'caste' => (string) ($e->caste() ?? ''),
                'preparations' => (string) ($e->preparations() ?? ''),
                'disposition' => (string) ($e->disposition() ?? ''),
                'occurrenceStatus' => (string) ($e->occurrenceStatus() ?? ''),
                'typeStatus' => (string) ($e->typeStatus() ?? ''),
                'habitat' => (string) ($e->habitat() ?? ''),
                'biome' => (string) ($e->biome() ?? ''),
                'occurrenceRemarks' => (string) ($e->occurrenceRemarks() ?? ''),
                'taxonomicNotes' => (string) ($e->taxonomicNotes() ?? ''),
            ];
        }

        return new ExportarEspecimenesSeleccionadosOutput(
            columnas: self::COLUMNAS,
            filas: $filas,
        );
    }

    /**
     * @param  Especimen[]  $especimenes
     * @return array<string, Taxon> taxonId → Taxon
     */
    private function cargarTaxones(array $especimenes): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn (Especimen $e) => $e->taxonId(), $especimenes)
        )));
        if ($ids === []) {
            return [];
        }

        $map = [];
        foreach ($this->taxonRepo->buscarPorIds($ids) as $t) {
            $map[(string) $t->id()] = $t;
        }

        return $map;
    }

    /**
     * Resuelve la jerarquía de cada taxón subiendo hasta la raíz (agrega los
     * ancestros que falten con consultas por lote).
     *
     * @param  array<string, Taxon>  $taxonesIniciales
     * @return array<string, array<string, ?string>> taxonId → [rango => nombre]
     */
    private function resolverJerarquias(array $taxonesIniciales): array
    {
        $todos = $taxonesIniciales;
        $pendientes = $taxonesIniciales;

        $iteraciones = 0;
        while ($pendientes !== [] && $iteraciones++ < 20) {
            $padreIds = [];
            foreach ($pendientes as $taxon) {
                if ($taxon->padreId() !== null && ! isset($todos[(string) $taxon->padreId()])) {
                    $padreIds[] = (string) $taxon->padreId();
                }
            }
            $padreIds = array_values(array_unique($padreIds));
            if ($padreIds === []) {
                break;
            }

            $nuevos = [];
            foreach ($this->taxonRepo->buscarPorIds($padreIds) as $padre) {
                $todos[(string) $padre->id()] = $padre;
                $nuevos[(string) $padre->id()] = $padre;
            }
            $pendientes = $nuevos;
        }

        $jerarquias = [];
        foreach ($todos as $idStr => $taxon) {
            $jerarquias[$idStr] = $this->camposJerarquicosDe($taxon, $todos);
        }

        return $jerarquias;
    }

    /**
     * @param  array<string, Taxon>  $todos
     * @return array<string, ?string>
     */
    private function camposJerarquicosDe(Taxon $taxon, array $todos): array
    {
        $resultado = [
            'kingdom' => null, 'phylum' => null, 'class' => null,
            'order' => null, 'family' => null, 'genus' => null,
        ];

        $actual = $taxon;
        $iteraciones = 0;
        while ($actual !== null && $iteraciones++ < 20) {
            match ($actual->rango()) {
                RangoTaxonomico::Reino => $resultado['kingdom'] = $actual->nombreCientifico(),
                RangoTaxonomico::Phylum => $resultado['phylum'] = $actual->nombreCientifico(),
                RangoTaxonomico::Clase => $resultado['class'] = $actual->nombreCientifico(),
                RangoTaxonomico::Orden => $resultado['order'] = $actual->nombreCientifico(),
                RangoTaxonomico::Familia => $resultado['family'] = $actual->nombreCientifico(),
                RangoTaxonomico::Genero => $resultado['genus'] = $actual->nombreCientifico(),
                default => null,
            };
            $padreId = $actual->padreId();
            $actual = $padreId !== null ? ($todos[(string) $padreId] ?? null) : null;
        }

        return $resultado;
    }
}
