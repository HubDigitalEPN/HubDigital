<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

/**
 * Construye la jerarquía taxonómica durante el import del Excel.
 *
 * Para cada fila lee kingdom/phylum/class/order/suborder/family/subfamily/
 * tribe/genus/specificEpithet y `taxonRank`. Crea o reutiliza taxones
 * encadenándolos vía `padre_id`. Devuelve el `TaxonId` del taxón al que
 * corresponde el espécimen — el del rango declarado en `taxonRank`, o el
 * de menor rango con dato si `taxonRank` está vacío.
 *
 * Decisiones aplicadas (P0.1 y P1-refinement):
 *  - `scientificName` para rango especie se construye como binomen
 *    `genus + ' ' + specificEpithet` (nunca el epíteto suelto).
 *  - `clade` se IGNORA (no es rango DwC).
 *  - `infraspecificEpithet` NO crea un rango propio; se devuelve aparte
 *    para que el caller lo asigne como atributo del taxón especie via
 *    `Taxon::actualizar()` después de crearlo. El mapper aún no lo usa.
 *
 * Cache: por instancia del constructor (typically: una corrida del importer).
 * Clave del cache: "rango|nombre_cientifico". Evita golpear el repo 48k veces
 * cuando los mismos taxones se repiten en muchas filas.
 */
final class ConstructorTaxonomiaImport
{
    /** Orden de los rangos del más alto al más bajo. */
    private const RANGOS_ORDENADOS = [
        ['col' => 'kingdom', 'rango' => RangoTaxonomico::Reino],
        ['col' => 'phylum', 'rango' => RangoTaxonomico::Phylum],
        ['col' => 'class', 'rango' => RangoTaxonomico::Clase],
        ['col' => 'order', 'rango' => RangoTaxonomico::Orden],
        ['col' => 'suborder', 'rango' => RangoTaxonomico::Suborden],
        ['col' => 'family', 'rango' => RangoTaxonomico::Familia],
        ['col' => 'subfamily', 'rango' => RangoTaxonomico::Subfamilia],
        ['col' => 'tribe', 'rango' => RangoTaxonomico::Tribu],
        ['col' => 'genus', 'rango' => RangoTaxonomico::Genero],
    ];

    /** @var array<string, TaxonId> Cache rango+nombre → TaxonId. */
    private array $cache = [];

    private bool $cachePrecargado = false;

    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    /**
     * Pre-carga TODOS los taxones existentes en memoria con una sola consulta.
     * Crítico para el importador masivo: sin esto, cada fila dispara hasta 9
     * SELECTs (uno por nivel jerárquico) contra la BD remota.
     */
    public function precargarTaxonomiaExistente(): void
    {
        if ($this->cachePrecargado) {
            return;
        }
        foreach ($this->taxonRepo->buscarTodos() as $taxon) {
            $clave = $taxon->rango()->value.'|'.$taxon->nombreCientifico();
            $this->cache[$clave] = $taxon->id();
        }
        $this->cachePrecargado = true;
    }

    /**
     * Resuelve la jerarquía completa de la fila y devuelve el TaxonId del taxón
     * al que se debe enganchar el espécimen. Devuelve null si no hay ningún
     * rango con dato.
     *
     * @param  array<string, string|null>  $fila  Array snake_case con kingdom,
     *                                            phylum, class, order, suborder,
     *                                            family, subfamily, tribe, genus,
     *                                            specific_epithet, taxon_rank.
     */
    public function resolverDeFila(array $fila): ?TaxonId
    {
        $padreId = null;
        $taxonesCreados = [];

        foreach (self::RANGOS_ORDENADOS as $nivel) {
            $nombre = $this->limpiar($fila[$nivel['col']] ?? null);
            if ($nombre === null) {
                continue;
            }
            $taxonId = $this->resolverNivel($nivel['rango'], $nombre, $padreId);
            $taxonesCreados[$nivel['rango']->value] = $taxonId;
            $padreId = $taxonId;
        }

        // Especie: requiere genus + specific_epithet (binomen).
        $genusNombre = $this->limpiar($fila['genus'] ?? null);
        $specificEpithet = $this->limpiar($fila['specific_epithet'] ?? null);
        if ($genusNombre !== null && $specificEpithet !== null) {
            $binomen = $genusNombre.' '.$specificEpithet;
            $genusId = $taxonesCreados['genero'] ?? $padreId;
            $taxonesCreados['especie'] = $this->resolverNivel(
                RangoTaxonomico::Especie,
                $binomen,
                $genusId,
            );
        }

        // Decidir cuál es el taxon_id del espécimen: el rango declarado en
        // taxonRank, o el de menor rango con dato como fallback.
        $rangoDeclarado = $this->mapearTaxonRank($this->limpiar($fila['taxon_rank'] ?? null));
        if ($rangoDeclarado !== null && isset($taxonesCreados[$rangoDeclarado->value])) {
            return $taxonesCreados[$rangoDeclarado->value];
        }

        // Fallback: el último (menor rango) creado.
        return $taxonesCreados === [] ? null : end($taxonesCreados);
    }

    private function resolverNivel(RangoTaxonomico $rango, string $nombre, ?TaxonId $padreId): TaxonId
    {
        $clave = $rango->value.'|'.$nombre;
        if (isset($this->cache[$clave])) {
            return $this->cache[$clave];
        }

        // Cache miss: si la taxonomía ya fue precargada en memoria, sabemos que
        // el taxón no existe en BD → saltamos el SELECT redundante y vamos
        // directo al INSERT.
        if (! $this->cachePrecargado) {
            $existente = $this->taxonRepo->buscarPorNombreYRango($nombre, $rango);
            if ($existente !== null) {
                $this->cache[$clave] = $existente->id();

                return $existente->id();
            }
        }

        $nuevo = Taxon::crear(
            id: $this->taxonRepo->nextIdentity(),
            nombreCientifico: $nombre,
            rango: $rango->value,
            padreId: $padreId !== null ? (string) $padreId : null,
        );
        $this->taxonRepo->guardar($nuevo);
        $this->cache[$clave] = $nuevo->id();

        return $nuevo->id();
    }

    private function mapearTaxonRank(?string $taxonRank): ?RangoTaxonomico
    {
        if ($taxonRank === null) {
            return null;
        }

        return RangoTaxonomico::desdeValorFlexible($taxonRank);
    }

    private function limpiar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);
        if ($trim === '' || mb_strtolower($trim) === 'na' || $trim === '-') {
            return null;
        }

        return $trim;
    }
}
