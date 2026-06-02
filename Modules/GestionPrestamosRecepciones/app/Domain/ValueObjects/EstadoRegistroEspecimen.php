<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

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
