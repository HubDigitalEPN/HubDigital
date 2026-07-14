<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverLotePorCodigoQr;

/** Input para resolver la solicitud de un lote a partir del Código QR escaneado. */
final readonly class ResolverLotePorCodigoQrInput
{
    public function __construct(
        public string $codigoQr,
    ) {}
}
