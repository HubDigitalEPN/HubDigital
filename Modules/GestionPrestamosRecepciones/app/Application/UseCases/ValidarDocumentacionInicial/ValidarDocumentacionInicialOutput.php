<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoDocumental;

/**
 * Datos de salida tras validar la documentación inicial.
 */
final readonly class ValidarDocumentacionInicialOutput
{
    public function __construct(
        public EstadoDocumental $estadoDocumental,
    ) {}
}
