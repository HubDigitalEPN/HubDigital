<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Anomalía no devolvible al investigador que se acepta con observaciones durante la
 * recepción física. Una contaminación por infección compromete la sanidad y obliga
 * a ingresar los especímenes en {@see EstadoColeccion::Cuarentena}; el resto de
 * observaciones no altera el destino de colección conforme al tipo de trámite.
 */
enum TipoObservacionRecepcion: string
{
    case ContaminacionPorInfeccion = 'Contaminación por infección';
    case OtrasObservaciones = 'Otras observaciones';

    /** Indica si la anomalía obliga a poner el lote en cuarentena. */
    public function requiereCuarentena(): bool
    {
        return $this === self::ContaminacionPorInfeccion;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
