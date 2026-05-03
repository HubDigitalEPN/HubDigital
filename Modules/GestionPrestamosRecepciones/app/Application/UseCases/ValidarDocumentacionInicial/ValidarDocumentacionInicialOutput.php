<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoDocumental;

final readonly class ValidarDocumentacionInicialOutput
{
    public function __construct(
        public EstadoDocumental $estadoDocumental,
    ) {}
}
