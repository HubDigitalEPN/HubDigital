<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidacionManualCuraduria;

/** Resultado de la corrección: valor guardado y cuántas celdas anómalas quedan. */
final readonly class ValidacionManualCuraduriaOutput
{
    public function __construct(
        public string $campo,
        public mixed $valorAnterior,
        public mixed $valorGuardado,
        public int $celdasAnomalasRestantes,
    ) {}
}
