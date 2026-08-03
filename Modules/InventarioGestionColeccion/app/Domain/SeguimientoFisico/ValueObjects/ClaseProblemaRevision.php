<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Clase de problema por la que un espécimen quedó marcado para revisión.
 *
 * `motivo_revision` es texto libre acumulado (el importador concatena varios
 * avisos con "; "), así que el dominio no puede tratarlo como un enum. Este VO
 * es la traducción de ese texto a categorías accionables: es lo que permite a
 * la hoja de inventario ofrecer "ir al problema" en vez de mostrar una cadena
 * cruda que el curador tiene que interpretar.
 *
 * Cada caso responde a una pregunta distinta: *dónde* se arregla esto. Por eso
 * `individualCount` y `occurrenceId` existen por separado aunque hoy ninguna
 * pantalla los corrija: distinguirlos evita prometer una corrección que no
 * existe (ver `esCorregible()`).
 */
enum ClaseProblemaRevision: string
{
    case Coordenadas = 'coordenadas';
    case Fecha = 'fecha';
    case Taxonomia = 'taxonomia';
    case OccurrenceId = 'occurrence_id';
    case IndividualCount = 'individual_count';
    case Generico = 'generico';

    /** Etiqueta corta para el tooltip de la fila. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Coordenadas => 'Corregir coordenadas',
            self::Fecha => 'Normalizar la fecha de colecta',
            self::Taxonomia => 'Resolver la determinación taxonómica',
            self::OccurrenceId => 'Sin identificador de ocurrencia',
            self::IndividualCount => 'Número de individuos no numérico',
            self::Generico => 'Revisar la ficha',
        };
    }

    /**
     * Si existe hoy una pantalla donde este problema se pueda ARREGLAR.
     *
     * `occurrenceId` e `individualCount` van en false a propósito: ninguno de
     * los dos está en `ActualizarEspecimenInput`, así que no hay formulario que
     * los edite. Marcarlos como corregibles llevaría al curador a una pantalla
     * donde la única acción posible es dar por buena la incidencia sin
     * resolverla.
     */
    public function esCorregible(): bool
    {
        return match ($this) {
            self::Coordenadas, self::Fecha, self::Taxonomia => true,
            self::OccurrenceId, self::IndividualCount, self::Generico => false,
        };
    }
}
