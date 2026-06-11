<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion;

/**
 * Output DTO para el caso de uso de declarar sin documentación.
 */
final readonly class DeclararSinDocumentacionOutput
{
    /**
     * @param bool $sinDocumentacion Indica si se declaró correctamente como sin documentación.
     */
    public function __construct(
        public bool $sinDocumentacion,
    ) {}
}
