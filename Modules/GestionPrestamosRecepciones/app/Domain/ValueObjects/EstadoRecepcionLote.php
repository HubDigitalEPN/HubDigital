<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Ciclo de vida de la recepción física de un lote de muestras.
 *
 * El lote nace {@see EnVerificacion} cuando el curador lo evalúa. Según el resultado
 * termina en {@see VerificadoFisicamente} o {@see VerificadoConObservaciones}
 * (estados terminales que habilitan el ingreso a la colección), o queda en
 * {@see RecepcionSuspendida} (no terminal) cuando la anomalía es subsanable y el
 * lote debe devolverse al investigador para reintentar la recepción.
 */
enum EstadoRecepcionLote: string
{
    case EnVerificacion = 'En Verificación';
    case VerificadoFisicamente = 'Verificado Físicamente';
    case RecepcionSuspendida = 'Recepción Suspendida';
    case VerificadoConObservaciones = 'Verificado con Observaciones';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
