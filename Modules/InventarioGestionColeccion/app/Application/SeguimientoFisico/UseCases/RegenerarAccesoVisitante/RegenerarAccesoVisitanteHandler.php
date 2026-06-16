<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegenerarAccesoVisitante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\VisitanteNoEncontradoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

/**
 * Emite una nueva versión de acceso para un visitante existente: el curador puede
 * generarle un QR fresco sin re-registrarlo. Al incrementar la versión, cualquier
 * QR anterior queda invalidado aunque no haya expirado.
 */
final class RegenerarAccesoVisitanteHandler
{
    public function __construct(
        private readonly VisitanteRepositoryInterface $visitanteRepo,
    ) {}

    public function handle(RegenerarAccesoVisitanteInput $input): RegenerarAccesoVisitanteOutput
    {
        if (! VisitanteId::esValido($input->visitanteId)) {
            throw new VisitanteNoEncontradoException($input->visitanteId);
        }

        $visitante = $this->visitanteRepo->buscarPorId(VisitanteId::desde($input->visitanteId));

        if ($visitante === null) {
            throw new VisitanteNoEncontradoException($input->visitanteId);
        }

        $visitante->regenerarAcceso();
        $this->visitanteRepo->guardar($visitante);

        return new RegenerarAccesoVisitanteOutput(
            id: $visitante->id(),
            nombre: $visitante->nombre(),
            versionAcceso: $visitante->versionAcceso(),
        );
    }
}
