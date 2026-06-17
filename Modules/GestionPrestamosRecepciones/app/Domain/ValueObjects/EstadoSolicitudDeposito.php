<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Estados del ciclo de vida de una solicitud de depósito
 * ({@see \Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito}):
 * en borrador, rechazada, retenida para asesoría curatorial o pendiente de revisión
 * por curaduría.
 */
enum EstadoSolicitudDeposito: string
{
    case EnBorrador = 'En Borrador';
    case Rechazada = 'Rechazada';
    case RetenidaParaAsesoriaCuratorial = 'Pausada para Asesoría';
    case PendienteDeRevisionPorCuraduria = 'Pendiente de Revisión por Curaduría';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
