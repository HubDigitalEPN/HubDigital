<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;

/**
 * Caso de uso: resolver una alerta de ubicación registrando el motivo de su resolución.
 *
 * @see ResolverAlertaInput
 * @see ResolverAlertaOutput
 */
final class ResolverAlertaHandler
{
    /**
     * @param  AlertaUbicacionRepository  $alertaRepo  Recupera y persiste la alerta.
     */
    public function __construct(
        private readonly AlertaUbicacionRepository $alertaRepo,
    ) {}

    /**
     * Recupera la alerta, la marca como resuelta con el motivo indicado y persiste el cambio,
     * devolviendo su estado resultante.
     *
     * @throws \DomainException si la alerta no existe.
     */
    public function handle(ResolverAlertaInput $input): ResolverAlertaOutput
    {
        $id = AlertaUbicacionId::desde($input->alertaId);
        $alerta = $this->alertaRepo->buscarPorId($id);

        if ($alerta === null) {
            throw new \DomainException("Alerta '{$input->alertaId}' no encontrada.");
        }

        $alerta->resolver($input->motivoResolucion);
        $this->alertaRepo->guardar($alerta);

        return new ResolverAlertaOutput(
            alertaId: (string) $alerta->id(),
            estado: $alerta->estado()->valor(),
        );
    }
}
