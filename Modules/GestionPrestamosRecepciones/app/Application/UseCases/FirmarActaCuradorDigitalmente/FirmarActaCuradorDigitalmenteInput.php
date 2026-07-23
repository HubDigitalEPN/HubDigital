<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaCuradorDigitalmente;

/**
 * Datos de entrada para que el curador firme el acta digitalmente (canvas).
 */
final readonly class FirmarActaCuradorDigitalmenteInput
{
    public function __construct(
        public string $actaId,
        public string $curadorId,
        public string $firmaBase64,
    ) {}
}
