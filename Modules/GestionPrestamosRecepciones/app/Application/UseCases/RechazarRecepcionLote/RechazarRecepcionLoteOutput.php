<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarRecepcionLote;

/** Output del rechazo subsanable: estado del lote, orden de acción correctiva, vigencia del QR y notificación. */
final readonly class RechazarRecepcionLoteOutput
{
    public function __construct(
        public string $estado,
        public string $ordenAccionCorrectiva,
        public bool $codigoQrVigente,
        public bool $notificacionInvestigadorEnviada,
    ) {}

    public static function fromPrimitives(
        string $estado,
        string $ordenAccionCorrectiva,
        bool $codigoQrVigente,
        bool $notificacionInvestigadorEnviada,
    ): self {
        return new self(
            estado: $estado,
            ordenAccionCorrectiva: $ordenAccionCorrectiva,
            codigoQrVigente: $codigoQrVigente,
            notificacionInvestigadorEnviada: $notificacionInvestigadorEnviada,
        );
    }
}
