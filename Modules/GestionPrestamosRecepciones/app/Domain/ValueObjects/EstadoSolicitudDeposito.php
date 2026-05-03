<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum EstadoSolicitudDeposito: string
{
    case EnBorrador = 'En Borrador';
    case Rechazada = 'Rechazada';
    case RetenidaParaAsesoriaCuratorial = 'Retenida para Asesoría Curatorial';
    case PendienteDeRevisionPorCuraduria = 'Pendiente de Revisión por Curaduría';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
