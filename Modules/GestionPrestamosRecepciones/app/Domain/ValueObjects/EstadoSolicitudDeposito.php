<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;

/**
 * Estados del ciclo de vida de una solicitud de depósito
 * ({@see SolicitudDeposito}):
 * en borrador, rechazada, retenida para asesoría curatorial, pendiente de revisión
 * por curaduría, y los estados resultantes de la decisión curatorial (aprobada
 * documentalmente, requiere corrección, rechazo permanente).
 */
enum EstadoSolicitudDeposito: string
{
    case EnBorrador = 'En Borrador';
    case Rechazada = 'Rechazada';
    case RetenidaParaAsesoriaCuratorial = 'Pausada para Asesoría';
    case PendienteDeRevisionPorCuraduria = 'Pendiente de Revisión por Curaduría';
    case AprobadaDocumentalmente = 'Aprobada Documentalmente';
    case RequiereCorreccion = 'Requiere Corrección';
    case RechazoPermanente = 'Rechazo Permanente';

    /**
     * El material depositado volvió a su depositante y el trámite quedó cerrado.
     *
     * Estado terminal, solo alcanzable desde una solicitud de Depósito ya aprobada:
     * una Donación es una cesión definitiva al patrimonio y no se devuelve.
     */
    case Devuelta = 'Devuelta';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
