<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Fallo de integridad subsanable detectado en la lista de verificación del lote.
 * Cada motivo determina la {@see AccionCorrectiva} que se ordena al investigador
 * antes de reintentar la recepción del mismo lote.
 */
enum MotivoFalloRecepcion: string
{
    case NivelAlcoholInsuficiente = 'Nivel de alcohol insuficiente';
    case FrascosRotos = 'Frascos rotos';
    case InconsistenciaEnConteo = 'Inconsistencia en conteo';

    /** Acción correctiva que corresponde al fallo. */
    public function accionCorrectiva(): AccionCorrectiva
    {
        return match ($this) {
            self::NivelAlcoholInsuficiente => AccionCorrectiva::CorreccionEnSitio,
            self::FrascosRotos => AccionCorrectiva::ReemplazoDeMaterial,
            self::InconsistenciaEnConteo => AccionCorrectiva::AuditoriaDeInventario,
        };
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
