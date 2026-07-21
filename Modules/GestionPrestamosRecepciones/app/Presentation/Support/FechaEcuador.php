<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Helper de presentación para mostrar fechas/horas en la zona horaria local
 * (Ecuador, America/Guayaquil, UTC−5).
 *
 * El almacenamiento permanece en UTC; la conversión ocurre solo al mostrar, para
 * no corromper los timestamps ya guardados.
 */
final class FechaEcuador
{
    public const ZONA = 'America/Guayaquil';

    /**
     * Formatea una fecha (interpretada como UTC) en la hora local de Ecuador.
     */
    public static function formatear(?DateTimeInterface $fecha, string $formato = 'd/m/Y H:i', string $vacio = '—'): string
    {
        if ($fecha === null) {
            return $vacio;
        }

        return CarbonImmutable::instance($fecha)->setTimezone(self::ZONA)->format($formato);
    }
}
