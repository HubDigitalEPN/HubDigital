<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote;

/** Input para aprobar la recepción conforme de un lote que superó la lista de verificación. */
final readonly class AprobarRecepcionLoteInput
{
    /**
     * @param  array<array{item: string, resultado: string}>  $itemsVerificacion
     */
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public array $itemsVerificacion,
    ) {}
}
