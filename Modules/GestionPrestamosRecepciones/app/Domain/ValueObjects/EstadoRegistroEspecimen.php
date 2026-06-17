<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Estado de validación de un registro de espécimen
 * ({@see \Modules\GestionPrestamosRecepciones\Domain\Entities\RegistroEspecimen}):
 * pendiente, validado técnicamente, corregido por sugerencia o en validación manual
 * por curaduría.
 */
enum EstadoRegistroEspecimen: string
{
    case Pendiente = 'Pendiente';
    case ValidadoTecnicamente = 'Validado Técnicamente';
    case CorregidoPorSugerencia = 'Corregido por Sugerencia';
    case ValidacionManualPorCuraduria = 'Validación Manual por Curaduría';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
