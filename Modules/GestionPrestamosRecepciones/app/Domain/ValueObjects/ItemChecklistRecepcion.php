<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Ítems de la lista de verificación de recepción física de un lote. Es el vocabulario
 * canónico del checklist: cada ítem conoce su resultado esperado cuando es conforme y
 * si su incumplimiento compromete la sanidad de los especímenes (lo que obliga a
 * cuarentena al aceptar con observaciones).
 */
enum ItemChecklistRecepcion: string
{
    case NivelAlcohol = 'Nivel de alcohol';
    case EstadoEspecimenes = 'Estado de los especímenes';
    case IntegridadFrascos = 'Integridad de los frascos';
    case CompletitudConteo = 'Completitud del conteo';

    /** Resultado que registra el ítem cuando el curador lo marca como conforme. */
    public function resultadoConforme(): string
    {
        return match ($this) {
            self::NivelAlcohol => 'Adecuado',
            self::EstadoEspecimenes => 'Sanos',
            self::IntegridadFrascos => 'Íntegros',
            self::CompletitudConteo => 'Exacto',
        };
    }

    /** Indica si el incumplimiento de este ítem compromete la sanidad (→ cuarentena). */
    public function comprometeSanidad(): bool
    {
        return $this === self::EstadoEspecimenes;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
