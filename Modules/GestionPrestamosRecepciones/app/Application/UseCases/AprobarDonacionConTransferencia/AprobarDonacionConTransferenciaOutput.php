<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia;

/**
 * Output DTO de la aprobación de una donación: incluye la ruta del Acta de
 * Transferencia de Dominio generada además del Código QR y la auditoría.
 */
final readonly class AprobarDonacionConTransferenciaOutput
{
    public function __construct(
        public string $estado,
        public ?string $curadorResponsable,
        public string $codigoQR,
        public bool $codigoQRDisponible,
        public string $actaTransferenciaDominioRuta,
        public bool $notificacionInvestigadorEnviada,
    ) {}

    public static function fromPrimitives(
        string $estado,
        ?string $curadorResponsable,
        string $codigoQR,
        bool $codigoQRDisponible,
        string $actaTransferenciaDominioRuta,
        bool $notificacionInvestigadorEnviada,
    ): self {
        return new self(
            estado: $estado,
            curadorResponsable: $curadorResponsable,
            codigoQR: $codigoQR,
            codigoQRDisponible: $codigoQRDisponible,
            actaTransferenciaDominioRuta: $actaTransferenciaDominioRuta,
            notificacionInvestigadorEnviada: $notificacionInvestigadorEnviada,
        );
    }
}
