<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverLotePorCodigoQr;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;

/**
 * Resuelve, a partir de un Código QR de lote escaneado, la identidad de la solicitud
 * que rotula, para que la capa de presentación enrute según el actor. Devuelve null si
 * el código tiene un formato inválido o no corresponde a ninguna solicitud.
 */
final class ResolverLotePorCodigoQrHandler
{
    public function __construct(
        private readonly SolicitudDepositoRepositoryInterface $solicitudRepo,
    ) {}

    public function handle(ResolverLotePorCodigoQrInput $input): ?ResolverLotePorCodigoQrOutput
    {
        try {
            $codigo = CodigoQRLote::from($input->codigoQr);
        } catch (\DomainException) {
            return null;
        }

        $solicitud = $this->solicitudRepo->buscarPorCodigoQR($codigo);

        if ($solicitud === null) {
            return null;
        }

        return new ResolverLotePorCodigoQrOutput(
            solicitudId: (string) $solicitud->id(),
            investigadorId: $solicitud->investigadorId(),
        );
    }
}
