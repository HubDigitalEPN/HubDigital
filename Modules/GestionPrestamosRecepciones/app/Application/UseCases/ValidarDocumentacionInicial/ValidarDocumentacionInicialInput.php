<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial;

/**
 * Datos de entrada para validar la documentación inicial.
 */
final readonly class ValidarDocumentacionInicialInput
{
    public function __construct(
        public string $solicitudId,
        public ?string $provinciaOrigen,
        /** @var array<string, string> [nombre => ruta] */
        public array $documentosAdjuntos,
    ) {}
}
