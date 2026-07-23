<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidacionManualCuraduria;

/** Corrección que el curador aplica sobre una celda anómala de la matriz. */
final readonly class ValidacionManualCuraduriaInput
{
    public function __construct(
        public string $solicitudId,
        public string $registroId,
        public string $campo,
        public string $valor,
        public string $curadorId,
    ) {}
}
