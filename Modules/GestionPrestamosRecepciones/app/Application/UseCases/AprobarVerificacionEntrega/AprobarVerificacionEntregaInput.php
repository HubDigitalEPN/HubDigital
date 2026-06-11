<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega;

/**
 * Datos de entrada para el caso de uso de aprobación de verificación de entrega.
 */
final readonly class AprobarVerificacionEntregaInput
{
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
    ) {}
}
