<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum TipoIdentificadorEspecimen: string
{
    case CodigoCatalogo = 'codigo_catalogo';
    case OccurrenceId = 'occurrence_id';
    case CatalogNumber = 'catalog_number';
    case OldCode = 'old_code';
    case CardexLiquidCollectionCode = 'cardex_liquid_collection_code';
    case OtherCatalogNumber = 'other_catalog_number';
    case VerifiedCode = 'verified_code';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
