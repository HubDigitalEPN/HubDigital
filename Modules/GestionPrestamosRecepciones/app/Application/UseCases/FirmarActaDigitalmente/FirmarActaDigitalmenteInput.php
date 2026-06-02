<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente;

final readonly class FirmarActaDigitalmenteInput
{
    public function __construct(
        public string $actaId,
        public string $investigadorId,
        public string $firmaBase64,
    ) {}
}
