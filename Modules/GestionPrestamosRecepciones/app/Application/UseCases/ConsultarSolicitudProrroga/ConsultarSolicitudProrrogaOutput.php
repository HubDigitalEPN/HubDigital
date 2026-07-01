<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudProrroga;

use DateTimeImmutable;

/**
 * Output DTO de lectura con los datos de la solicitud de prórroga pendiente.
 */
final readonly class ConsultarSolicitudProrrogaOutput
{
    public function __construct(
        public string $solicitudProrrogaId,
        public string $prestamoId,
        public DateTimeImmutable $fechaPropuesta,
        public string $justificacion,
        public string $estado,
        public DateTimeImmutable $solicitadaEn,
    ) {}
}
