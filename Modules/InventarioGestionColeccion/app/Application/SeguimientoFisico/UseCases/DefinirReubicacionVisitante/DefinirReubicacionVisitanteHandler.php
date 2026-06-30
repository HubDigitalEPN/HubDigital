<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DefinirReubicacionVisitante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

/**
 * Caso de uso: habilitar o revocar que un visitante pueda reubicar especímenes.
 *
 * @see DefinirReubicacionVisitanteInput
 * @see DefinirReubicacionVisitanteOutput
 */
final class DefinirReubicacionVisitanteHandler
{
    public function __construct(
        private readonly VisitanteRepositoryInterface $visitanteRepo,
    ) {}

    public function handle(DefinirReubicacionVisitanteInput $input): DefinirReubicacionVisitanteOutput
    {
        $visitante = $this->visitanteRepo->buscarPorId(VisitanteId::desde($input->visitanteId));
        if ($visitante === null) {
            throw new \DomainException("Visitante '{$input->visitanteId}' no encontrado.");
        }

        $visitante->definirReubicacion($input->habilitado);
        $this->visitanteRepo->guardar($visitante);

        return new DefinirReubicacionVisitanteOutput(
            visitanteId: $input->visitanteId,
            puedeReubicar: $visitante->puedeReubicar(),
        );
    }
}
