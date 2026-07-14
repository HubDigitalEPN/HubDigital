<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverLotePorCodigoQr;

/** Identidad mínima de la solicitud resuelta desde el Código QR, para enrutar según el actor. */
final readonly class ResolverLotePorCodigoQrOutput
{
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
    ) {}
}
