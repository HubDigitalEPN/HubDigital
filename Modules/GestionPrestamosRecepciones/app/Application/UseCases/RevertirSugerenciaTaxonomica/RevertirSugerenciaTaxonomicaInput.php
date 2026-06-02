<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RevertirSugerenciaTaxonomica;

final readonly class RevertirSugerenciaTaxonomicaInput
{
    public function __construct(
        public string $solicitudId,
        public string $matrizId,
        public string $registroId,
    ) {}
}
