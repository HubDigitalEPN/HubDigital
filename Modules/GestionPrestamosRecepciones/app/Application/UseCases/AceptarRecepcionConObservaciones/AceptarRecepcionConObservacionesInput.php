<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones;

/** Input para aceptar la recepción de un lote con observaciones derivadas de los ítems no conformes. */
final readonly class AceptarRecepcionConObservacionesInput
{
    /**
     * @param  list<string>  $itemsNoConformes  Etiquetas de los ítems del checklist marcados como no conformes.
     */
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public array $itemsNoConformes,
        public ?string $comentario = null,
    ) {}
}
