<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaPrestamos;

/**
 * Resultado de la consulta de una bandeja de préstamos.
 *
 * {@see ConsultarBandejaPrestamosHandler}
 */
final readonly class ConsultarBandejaPrestamosOutput
{
    /**
     * @param array<int, FilaPrestamoBandeja> $filas
     */
    public function __construct(
        public array $filas,
    ) {}
}
