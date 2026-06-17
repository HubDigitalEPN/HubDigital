<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida;

/**
 * Output DTO para el caso de uso de determinar la documentación requerida.
 */
final readonly class DeterminarDocumentacionRequeridaOutput
{
    /**
     * @param string[] $documentosRequeridos Lista de documentos requeridos.
     */
    public function __construct(
        public array $documentosRequeridos,
    ) {}
}
