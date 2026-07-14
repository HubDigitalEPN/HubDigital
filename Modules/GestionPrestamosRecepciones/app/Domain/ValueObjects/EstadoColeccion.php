<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Estado con el que los especímenes de un lote ingresan a la colección tras la
 * recepción física. En una recepción conforme el destino depende del tipo de
 * trámite (los depósitos ingresan de forma {@see Temporal}, las donaciones de forma
 * {@see Permanente}); una anomalía no devolvible que compromete la sanidad los
 * envía a {@see Cuarentena}.
 */
enum EstadoColeccion: string
{
    case Temporal = 'Temporal';
    case Permanente = 'Permanente';
    case Cuarentena = 'Cuarentena';

    /** Destino de colección de un ingreso conforme según el tipo de trámite. */
    public static function porTipoTramite(TipoTramite $tipoTramite): self
    {
        return match ($tipoTramite) {
            TipoTramite::Deposito => self::Temporal,
            TipoTramite::Donacion => self::Permanente,
        };
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
