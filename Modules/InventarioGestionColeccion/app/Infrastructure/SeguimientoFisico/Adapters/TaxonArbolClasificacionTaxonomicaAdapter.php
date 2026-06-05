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

    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function resolverParaTaxon(string $taxonId): ?ClasificacionTaxonomica
    {
        try {
            $actualId = TaxonId::desde($taxonId);
        } catch (\InvalidArgumentException) {
            return null;
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

            $taxon = $this->taxonRepo->buscarPorId($actualId);
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

        return $clasificacion->estaVacia() ? null : $clasificacion;
    }
}
