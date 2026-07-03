<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GuardarPatenteAnual;

/**
 * Input DTO para registrar/cambiar la patente del laboratorio de un año.
 */
final readonly class GuardarPatenteAnualInput
{
    /**
     * @param int $anio Año al que aplica la patente.
     * @param string $codigo Código de patente (ej. MAATE-MCMEVS-2023-276).
     * @param string $curadorId ID del curador que la registra.
     */
    public function __construct(
        public int $anio,
        public string $codigo,
        public string $curadorId,
    ) {}
}
