<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion;

/** Input de lectura del detalle de recepción física de un lote, por id de solicitud. */
final readonly class ConsultarDetalleRecepcionInput
{
    public function __construct(
        public string $solicitudId,
    ) {}
}
