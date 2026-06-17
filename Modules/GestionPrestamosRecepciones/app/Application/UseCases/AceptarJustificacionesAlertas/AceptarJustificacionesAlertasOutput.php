<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarJustificacionesAlertas;

/**
 * Output DTO tras aceptar las justificaciones y aprobar documentalmente la solicitud.
 */
final readonly class AceptarJustificacionesAlertasOutput
{
    public function __construct(
        public string $estado,
        public ?string $curadorResponsable,
        public string $codigoQR,
        public bool $codigoQRDisponible,
        public bool $notificacionInvestigadorEnviada,
    ) {}

    public static function fromPrimitives(
        string $estado,
        ?string $curadorResponsable,
        string $codigoQR,
        bool $codigoQRDisponible,
        bool $notificacionInvestigadorEnviada,
    ): self {
        return new self(
            estado: $estado,
            curadorResponsable: $curadorResponsable,
            codigoQR: $codigoQR,
            codigoQRDisponible: $codigoQRDisponible,
            notificacionInvestigadorEnviada: $notificacionInvestigadorEnviada,
        );
    }
}
