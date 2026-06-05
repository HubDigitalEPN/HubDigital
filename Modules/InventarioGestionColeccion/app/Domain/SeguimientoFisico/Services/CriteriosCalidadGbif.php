<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;

/**
 * Servicio de dominio: decide si un espécimen es publicable a GBIF.
 *
 * Spec §P7.2: las reglas de calidad NO viven en la BD. La BD es permisiva
 * (acepta verbatims y FKs nullable). Es aquí donde se filtra antes de
 * exportar al Darwin Core Archive.
 *
 * Reglas de exclusión (cualquiera dispara la exclusión):
 *  - `taxon_id` ausente — sin determinación taxonómica firme.
 *  - `decimal_latitude` o `decimal_longitude` ausentes — sin georreferenciación.
 *  - `estado_revision = descartada` — el curador rechazó el registro.
 *
 * Reglas adicionales que NO excluyen (sólo se reportan):
 *  - `estado_revision = pendiente` se publica igual (los datos son válidos
 *    aunque no haya sido confirmada la revisión). Cambiar a futuro si el
 *    equipo decide que pendientes no salen.
 */
final class CriteriosCalidadGbif
{
    public function evaluar(Especimen $especimen): ResultadoCalidadGbif
    {
        $motivos = [];

        if ($especimen->taxonId() === null) {
            $motivos[] = 'sin taxón identificado';
        }

        if ($especimen->decimalLatitude() === null || $especimen->decimalLongitude() === null) {
            $motivos[] = 'sin coordenadas geográficas';
        }

        if ($especimen->estadoRevision() === EstadoRevision::Descartada) {
            $motivos[] = 'descartado por el curador';
        }

        return $motivos === [] ? ResultadoCalidadGbif::publicable() : ResultadoCalidadGbif::excluido($motivos);
    }
}
