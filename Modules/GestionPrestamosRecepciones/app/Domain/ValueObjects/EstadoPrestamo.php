<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum EstadoPrestamo: string
{
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
