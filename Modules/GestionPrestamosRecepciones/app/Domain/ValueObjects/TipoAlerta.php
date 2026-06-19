<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Tipos de alerta que pueden acompañar a una solicitud cuando llega a revisión
 * por curaduría y que el investigador debe justificar: discrepancia de identidad
 * con un tercero, especie no listada en el catálogo o hallazgo taxonómico no
 * catalogado.
 */
enum TipoAlerta: string
{
    case DiscrepanciaIdentidadTercero = 'Discrepancia de Identidad (Tercero)';
    case EspecieNoListadaCatalogo = 'Especie no listada en catálogo';
    case HallazgoTaxonomicoNoCatalogado = 'Hallazgo Taxonómico no Catalogado';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
