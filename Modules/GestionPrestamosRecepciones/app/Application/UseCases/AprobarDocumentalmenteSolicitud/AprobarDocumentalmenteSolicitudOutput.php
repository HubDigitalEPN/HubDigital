<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud;

/**
 * Output DTO de la aprobación documental: estado resultante, auditoría del curador
 * responsable, Código QR asignado al lote y notificación al investigador.
 */
final readonly class AprobarDocumentalmenteSolicitudOutput
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
