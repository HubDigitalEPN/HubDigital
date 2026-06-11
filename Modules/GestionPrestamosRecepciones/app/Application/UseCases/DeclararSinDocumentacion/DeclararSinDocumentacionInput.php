<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion;

/**
 * Input DTO para el caso de uso de declarar que no se dispone de documentación para la solicitud.
 */
final readonly class DeclararSinDocumentacionInput
{
    /**
     * @param string $solicitudId ID de la solicitud de depósito.
     */
    public function __construct(
        public string $solicitudId,
    ) {}
}
