<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada;

/**
 * Datos de entrada para validar un acta adjuntando el PDF firmado por el curador.
 */
final readonly class ValidarActaFirmadaInput
{
    public function __construct(
        public string $actaId,
        public string $curadorId,
        public string $pdfFirmadoCuradorRuta,
    ) {}
}
