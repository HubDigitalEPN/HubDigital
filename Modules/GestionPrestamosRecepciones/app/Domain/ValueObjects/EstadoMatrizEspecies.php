<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

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
