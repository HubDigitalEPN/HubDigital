<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial;

final readonly class CargarDocumentacionOficialInput
{
    public function __construct(
        public string $solicitudId,
        /** @var array<string, string> [nombre => ruta] */
        public array $documentos,
    ) {}
}
