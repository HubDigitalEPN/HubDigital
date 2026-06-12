<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo;

/**
 * Datos de entrada para registrar el envío de devolución de un préstamo por parte del investigador.
 */
final readonly class RegistrarDevolucionPrestamoInput
{
    public function __construct(
        public string $prestamoId,
        public string $investigadorId,
    ) {}
}
