<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia;

/**
 * Input DTO para que el curador apruebe documentalmente una donación, generando el
 * Acta de Transferencia de Dominio.
 */
final readonly class AprobarDonacionConTransferenciaInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
    ) {}
}
