<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DatasetConfig;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\DatasetConfigRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\CriteriosCalidadGbif;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;

/**
 * Exportador a Darwin Core Archive (formato GBIF/IPT).
 *
 * Genera 4 archivos como strings:
 *  - `meta.xml`    schema del archivo (lista de columnas y core/extensions).
 *  - `eml.xml`     metadatos del dataset desde DatasetConfig.
 *  - `occurrence.txt` TSV de especímenes publicables.
 *  - `taxon.txt`   TSV de taxones usados.
 *
 * Filtra con CriteriosCalidadGbif. occurrence_id formato MEPN:INV:{uuid}
 * (decisión P0.1: el UUID interno garantiza unicidad absoluta).
 */
final class ExportarDarwinCoreArchiveHandler
{
    private const OCCURRENCE_COLUMNS = [
        'occurrenceID', 'basisOfRecord', 'institutionCode', 'collectionCode',
        'catalogNumber', 'recordedBy', 'eventDate', 'eventDateEnd',
        'decimalLatitude', 'decimalLongitude', 'geodeticDatum',
        'country', 'stateProvince', 'municipality', 'locality',
        'scientificName', 'taxonID', 'taxonRank',
        'kingdom', 'phylum', 'class', 'order', 'family', 'genus',
        'individualCount', 'sex', 'lifeStage', 'caste',
        'preparations', 'typeStatus', 'occurrenceStatus',
        'habitat', 'biome',
        'occurrenceRemarks', 'taxonomicNotes',
    ];

    private const TAXON_COLUMNS = [
        'taxonID', 'scientificName', 'taxonRank', 'parentNameUsageID',
        'scientificNameAuthorship', 'kingdom', 'phylum', 'class', 'order',
        'family', 'genus',
    ];

    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly DatasetConfigRepositoryInterface $datasetConfigRepo,
        private readonly CriteriosCalidadGbif $criterios,
    ) {}

    public function handle(ExportarDarwinCoreArchiveInput $input): ExportarDarwinCoreArchiveOutput
    {
        $config = $this->datasetConfigRepo->obtenerActual();
        if ($config === null) {
            throw new \DomainException(
                'No hay DatasetConfig configurado. Configura institutionCode y collectionCode antes de exportar.'
            );
        }

        $todosEspecimenes = $this->especimenRepo->buscarTodos();

        $publicables = [];
        $motivosExclusion = [];
        $excluidos = 0;
        foreach ($todosEspecimenes as $especimen) {
            $resultado = $this->criterios->evaluar($especimen);
            if ($resultado->esPublicable) {
                $publicables[] = $especimen;

                continue;
            }
            $excluidos++;
            foreach ($resultado->motivosExclusion as $motivo) {
                $motivosExclusion[$motivo] = ($motivosExclusion[$motivo] ?? 0) + 1;
            }
        }
        arsort($motivosExclusion);

        $taxonesUsados = $this->cargarTaxonesUsados($publicables);
        $jerarquias = $this->resolverJerarquias($taxonesUsados);

        $occurrenceTxt = $this->generarOccurrenceTxt($publicables, $config, $taxonesUsados, $jerarquias);
        $taxonTxt = $this->generarTaxonTxt($taxonesUsados, $jerarquias);
        $metaXml = $this->generarMetaXml();
        $emlXml = $this->generarEmlXml($config);

        return new ExportarDarwinCoreArchiveOutput(
            metaXml: $metaXml,
            emlXml: $emlXml,
            occurrenceTxt: $occurrenceTxt,
            taxonTxt: $taxonTxt,
            totalEspecimenes: count($todosEspecimenes),
            publicados: count($publicables),
            excluidos: $excluidos,
            motivosExclusion: $motivosExclusion,
        );
    }

    /**
     * @param  Especimen[]  $publicables
     * @return array<string, Taxon> taxonId → Taxon
     */
    private function cargarTaxonesUsados(array $publicables): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn (Especimen $e) => $e->taxonId(), $publicables)
        )));
        if ($ids === []) {
            return [];
        }
        $taxones = $this->taxonRepo->buscarPorIds($ids);
        $map = [];
        foreach ($taxones as $t) {
            $map[(string) $t->id()] = $t;
        }

        return $map;
    }

    /**
     * @param  array<string, Taxon>  $taxonesIniciales
     * @return array<string, array<string, ?string>> taxonId → [rango => nombre]
     */
    private function resolverJerarquias(array $taxonesIniciales): array
    {
        $jerarquias = [];
        $pendientesParaResolver = $taxonesIniciales;

        // Walk-up: agregamos los padres faltantes a la búsqueda.
        $iteraciones = 0;
        while ($pendientesParaResolver !== [] && $iteraciones++ < 20) {
            $padreIdsAResolver = [];
            foreach ($pendientesParaResolver as $taxon) {
                if ($taxon->padreId() !== null && ! isset($taxonesIniciales[(string) $taxon->padreId()])) {
                    $padreIdsAResolver[] = (string) $taxon->padreId();
                }
            }
            $padreIdsAResolver = array_values(array_unique($padreIdsAResolver));
            if ($padreIdsAResolver === []) {
                break;
            }
            $padres = $this->taxonRepo->buscarPorIds($padreIdsAResolver);
            $nuevos = [];
            foreach ($padres as $padre) {
                $taxonesIniciales[(string) $padre->id()] = $padre;
                $nuevos[(string) $padre->id()] = $padre;
            }
            $pendientesParaResolver = $nuevos;
        }

        foreach ($taxonesIniciales as $idStr => $taxon) {
            $jerarquias[$idStr] = $this->camposJerarquicosDe($taxon, $taxonesIniciales);
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
            'kingdom' => null,
            'phylum' => null,
            'class' => null,
            'order' => null,
            'family' => null,
            'genus' => null,
        ];

        $actual = $taxon;
        $iteraciones = 0;
        while ($actual !== null && $iteraciones++ < 20) {
            $rango = $actual->rango();
            $nombre = $actual->nombreCientifico();
            match ($rango) {
                RangoTaxonomico::Reino => $resultado['kingdom'] = $nombre,
                RangoTaxonomico::Phylum => $resultado['phylum'] = $nombre,
                RangoTaxonomico::Clase => $resultado['class'] = $nombre,
                RangoTaxonomico::Orden => $resultado['order'] = $nombre,
                RangoTaxonomico::Familia => $resultado['family'] = $nombre,
                RangoTaxonomico::Genero => $resultado['genus'] = $nombre,
                default => null,
            };
            $padreId = $actual->padreId();
            $actual = $padreId !== null ? ($todos[(string) $padreId] ?? null) : null;
        }

        return $resultado;
    }

    /**
     * @param  Especimen[]  $publicables
     * @param  array<string, Taxon>  $taxones
     * @param  array<string, array<string, ?string>>  $jerarquias
     */
    private function generarOccurrenceTxt(
        array $publicables,
        DatasetConfig $config,
        array $taxones,
        array $jerarquias,
    ): string {
        $lineas = [implode("\t", self::OCCURRENCE_COLUMNS)];
        foreach ($publicables as $e) {
            $taxonId = $e->taxonId();
            $taxon = $taxonId !== null ? ($taxones[$taxonId] ?? null) : null;
            $jerarquia = $taxonId !== null ? ($jerarquias[$taxonId] ?? []) : [];

            $valores = [
                'occurrenceID' => 'MEPN:INV:'.((string) $e->id()),
                'basisOfRecord' => $config->basisOfRecord()->value,
                'institutionCode' => $config->institutionCode()->value,
                'collectionCode' => $config->collectionCode()->value,
                'catalogNumber' => $e->catalogNumber() ?? $e->codigoCatalogo(),
                'recordedBy' => $e->colector(),
                'eventDate' => $e->fechaColecta(),
                'eventDateEnd' => $e->fechaColectaFin() ?? '',
                'decimalLatitude' => $e->decimalLatitude() !== null ? (string) $e->decimalLatitude() : '',
                'decimalLongitude' => $e->decimalLongitude() !== null ? (string) $e->decimalLongitude() : '',
                'geodeticDatum' => $e->geodeticDatum() ?? '',
                'country' => $e->country() ?? '',
                'stateProvince' => $e->stateProvince() ?? '',
                'municipality' => $e->municipality() ?? '',
                'locality' => $e->localityName() ?? $e->localidad(),
                'scientificName' => $taxon?->nombreCientifico() ?? '',
                'taxonID' => $taxonId ?? '',
                'taxonRank' => $taxon?->rango()->value ?? '',
                'kingdom' => $jerarquia['kingdom'] ?? '',
                'phylum' => $jerarquia['phylum'] ?? '',
                'class' => $jerarquia['class'] ?? '',
                'order' => $jerarquia['order'] ?? '',
                'family' => $jerarquia['family'] ?? '',
                'genus' => $jerarquia['genus'] ?? '',
                'individualCount' => $e->individualCount() !== null ? (string) $e->individualCount() : '',
                'sex' => $e->sex() ?? '',
                'lifeStage' => $e->lifeStage() ?? '',
                'caste' => $e->caste() ?? '',
                'preparations' => $e->preparations() ?? '',
                'typeStatus' => $e->typeStatus() ?? '',
                'occurrenceStatus' => $e->occurrenceStatus() ?? '',
                'habitat' => $e->habitat() ?? '',
                'biome' => $e->biome() ?? '',
                'occurrenceRemarks' => $e->occurrenceRemarks() ?? '',
                'taxonomicNotes' => $e->taxonomicNotes() ?? '',
            ];

            $lineas[] = implode("\t", array_map(
                fn ($col) => $this->escaparTsv((string) ($valores[$col] ?? '')),
                self::OCCURRENCE_COLUMNS
            ));
        }

        return implode("\n", $lineas)."\n";
    }

    /**
     * @param  array<string, Taxon>  $taxones
     * @param  array<string, array<string, ?string>>  $jerarquias
     */
    private function generarTaxonTxt(array $taxones, array $jerarquias): string
    {
        $lineas = [implode("\t", self::TAXON_COLUMNS)];
        foreach ($taxones as $idStr => $t) {
            $jerarquia = $jerarquias[$idStr] ?? [];
            $valores = [
                'taxonID' => $idStr,
                'scientificName' => $t->nombreCientifico(),
                'taxonRank' => $t->rango()->value,
                'parentNameUsageID' => $t->padreId() !== null ? (string) $t->padreId() : '',
                'scientificNameAuthorship' => $t->autor() ?? '',
                'kingdom' => $jerarquia['kingdom'] ?? '',
                'phylum' => $jerarquia['phylum'] ?? '',
                'class' => $jerarquia['class'] ?? '',
                'order' => $jerarquia['order'] ?? '',
                'family' => $jerarquia['family'] ?? '',
                'genus' => $jerarquia['genus'] ?? '',
            ];

            $lineas[] = implode("\t", array_map(
                fn ($col) => $this->escaparTsv((string) ($valores[$col] ?? '')),
                self::TAXON_COLUMNS
            ));
        }

        return implode("\n", $lineas)."\n";
    }

    private function generarMetaXml(): string
    {
        $occCols = '';
        foreach (self::OCCURRENCE_COLUMNS as $i => $col) {
            $occCols .= "    <field index=\"{$i}\" term=\"http://rs.tdwg.org/dwc/terms/{$col}\"/>\n";
        }
        $taxCols = '';
        foreach (self::TAXON_COLUMNS as $i => $col) {
            $taxCols .= "    <field index=\"{$i}\" term=\"http://rs.tdwg.org/dwc/terms/{$col}\"/>\n";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<archive xmlns="http://rs.tdwg.org/dwc/text/" metadata="eml.xml">
  <core encoding="UTF-8" fieldsTerminatedBy="\\t" linesTerminatedBy="\\n" fieldsEnclosedBy="" ignoreHeaderLines="1" rowType="http://rs.tdwg.org/dwc/terms/Occurrence">
    <files><location>occurrence.txt</location></files>
    <id index="0"/>
{$occCols}  </core>
  <extension encoding="UTF-8" fieldsTerminatedBy="\\t" linesTerminatedBy="\\n" fieldsEnclosedBy="" ignoreHeaderLines="1" rowType="http://rs.tdwg.org/dwc/terms/Taxon">
    <files><location>taxon.txt</location></files>
    <coreid index="0"/>
{$taxCols}  </extension>
</archive>

XML;
    }

    private function generarEmlXml(DatasetConfig $config): string
    {
        $titulo = $this->escaparXml($config->emlTitulo() ?? $config->datasetName() ?? 'MEPN Catálogo de Invertebrados');
        $contacto = $this->escaparXml($config->emlContacto() ?? $config->rightsHolder() ?? '');
        $rightsHolder = $this->escaparXml($config->rightsHolder() ?? '');
        $license = $this->escaparXml($config->license()?->value ?? '');
        $institution = $this->escaparXml($config->institutionCode()->value);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eml:eml xmlns:eml="https://eml.ecoinformatics.org/eml-2.2.0"
         xmlns:dc="http://purl.org/dc/terms/"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         packageId="{$institution}.catalogo.invertebrados" system="http://gbif.org">
  <dataset>
    <title>{$titulo}</title>
    <creator><organizationName>{$institution}</organizationName></creator>
    <contact><electronicMailAddress>{$contacto}</electronicMailAddress></contact>
    <intellectualRights>
      <para>Licencia: {$license}</para>
      <para>Titular: {$rightsHolder}</para>
    </intellectualRights>
  </dataset>
</eml:eml>

XML;
    }

    private function escaparTsv(string $valor): string
    {
        return str_replace(["\t", "\n", "\r"], [' ', ' ', ' '], $valor);
    }

    private function escaparXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
