<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito;

final readonly class ActualizarOrigenSolicitudDepositoInput
{
    public function __construct(
        public string $solicitudId,
        public string $origenRecoleccion,
        public string $situacionRegulatoria,
        public ?string $provinciaOrigen = null,
    ) {}
}
