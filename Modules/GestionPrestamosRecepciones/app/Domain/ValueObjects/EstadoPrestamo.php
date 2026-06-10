<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Estados del ciclo de vida de un préstamo
 * ({@see \Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo}).
 *
 * Flujo principal: pendiente del documento del ministerio (internacional) o en
 * tránsito (nacional) → pendiente de aprobación de verificación → activo → vencido
 * o cerrado. Incluye además estados de prórroga y revisión.
 */
enum EstadoPrestamo: string
{
    case PendienteDocumentoMinisterio = 'pendiente_documento_ministerio';
    case EnTransito = 'en_transito';
    case PendienteAprobacionVerificacion = 'pendiente_aprobacion_verificacion';
    case Activo = 'activo';
    case ProrrogaSolicitada = 'prorroga_solicitada';
    case Vencido = 'vencido';
    case EnRevision = 'en_revision';
    case Cerrado = 'cerrado';
    case CerradoConObservacion = 'cerrado_con_observacion';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
