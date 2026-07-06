<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote;

/** Input para iniciar la recepción física del lote de una solicitud aprobada documentalmente. */
final readonly class IniciarRecepcionLoteInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
    ) {}
}
