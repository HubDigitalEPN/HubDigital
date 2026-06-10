<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Estado de validación de una matriz de especies
 * ({@see \Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies}):
 * pendiente, validada técnicamente o cargada con alertas (hallazgos por justificar).
 */
enum EstadoMatrizEspecies: string
{
    case Pendiente = 'Pendiente';
    case ValidadaTecnicamente = 'Validada Técnicamente';
    case CargadaConAlertas = 'Cargada con Alertas';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
