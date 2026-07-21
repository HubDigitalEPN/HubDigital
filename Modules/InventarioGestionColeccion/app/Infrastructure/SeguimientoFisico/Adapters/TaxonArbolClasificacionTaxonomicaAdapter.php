<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

/**
 * Resuelve la ClasificacionTaxonomica recorriendo el árbol de Taxones hacia la raíz
 * vía padreId, recogiendo el nombre científico de cada rango encontrado.
 *
 * El módulo de taxonomía modela Orden, Familia, Subfamilia, Género y Especie; los
 * niveles suborden y superfamilia del VO quedan en null porque la taxonomía no los expone.
 */
final class TaxonArbolClasificacionTaxonomicaAdapter implements ClasificacionTaxonomicaPort
{
    private const PROFUNDIDAD_MAXIMA = 30;

    /**
     * Cache por taxonId (incluye resultados null) para no repetir la caminata al padre: el
     * mismo taxón se resuelve muchas veces en una sola operación (especímenes hermanos,
     * recalculo del tray y luego de la caja). Vive mientras viva esta instancia; el binding
     * en el ServiceProvider es singleton para que se comparta durante toda la request.
     *
     * @var array<string, ClasificacionTaxonomica|null>
     */
    private array $cache = [];

    /** Cache de filas de Taxon por id: distintos especímenes comparten ancestros (género, familia...). */
    private array $taxonCache = [];

    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    /**
     * Construye la clasificación taxonómica de un taxón subiendo por la cadena de padres
     * hasta la raíz y quedándose con el primer nombre científico de cada rango encontrado.
     * Está protegido contra ciclos (registro de visitados) y contra árboles muy profundos
     * (límite de profundidad). Devuelve null si el id es inválido o si no se reúne ningún
     * rango, evitando propagar clasificaciones vacías al resto del componente.
     */
    public function resolverParaTaxon(string $taxonId): ?ClasificacionTaxonomica
    {
        if (array_key_exists($taxonId, $this->cache)) {
            return $this->cache[$taxonId];
        }

        try {
            $actualId = TaxonId::desde($taxonId);
        } catch (\InvalidArgumentException) {
            return $this->cache[$taxonId] = null;
        }

        $niveles = [];
        $visitados = [];
        $restante = self::PROFUNDIDAD_MAXIMA;

        while ($actualId !== null && $restante-- > 0) {
            $clave = (string) $actualId;
            if (isset($visitados[$clave])) {
                break;
            }
            $visitados[$clave] = true;

            $taxon = array_key_exists($clave, $this->taxonCache)
                ? $this->taxonCache[$clave]
                : $this->taxonCache[$clave] = $this->taxonRepo->buscarPorId($actualId);
            if ($taxon === null) {
                break;
            }

            $niveles[$taxon->rango()->value] ??= $taxon->nombreCientifico();

            $actualId = $taxon->padreId();
        }

        $clasificacion = ClasificacionTaxonomica::desde(
            orden: $niveles[RangoTaxonomico::Orden->value] ?? null,
            familia: $niveles[RangoTaxonomico::Familia->value] ?? null,
            subfamilia: $niveles[RangoTaxonomico::Subfamilia->value] ?? null,
            genero: $niveles[RangoTaxonomico::Genero->value] ?? null,
            especie: $niveles[RangoTaxonomico::Especie->value] ?? null,
        );

        return $this->cache[$taxonId] = ($clasificacion->estaVacia() ? null : $clasificacion);
    }
}
