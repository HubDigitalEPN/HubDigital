<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaActas;

/**
 * Resultado de la consulta de la bandeja de actas del curador.
 *
 * {@see ConsultarBandejaActasHandler}
 */
final readonly class ConsultarBandejaActasOutput
{
    /**
     * @param array<int, FilaActaBandeja> $filas
     */
    public function __construct(
        public array $filas,
    ) {}
}
