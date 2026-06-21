<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IgnorarAlerta;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;

/**
 * Caso de uso: marcar una alerta de ubicación como ignorada, descartándola sin resolverla
 * cuando el curador decide que no requiere acción.
 *
 * @see IgnorarAlertaInput
 * @see IgnorarAlertaOutput
 */
final class IgnorarAlertaHandler
{
    /**
     * @param  AlertaUbicacionRepository  $alertaRepo  Recupera y persiste la alerta.
     */
    public function __construct(
        private readonly AlertaUbicacionRepository $alertaRepo,
    ) {}

    /**
     * Recupera la alerta, la marca como ignorada y persiste el cambio, devolviendo su estado resultante.
     *
     * @throws \DomainException si la alerta no existe.
     */
    public function handle(IgnorarAlertaInput $input): IgnorarAlertaOutput
    {
        $id = AlertaUbicacionId::desde($input->alertaId);
        $alerta = $this->alertaRepo->buscarPorId($id);

        if ($alerta === null) {
            throw new \DomainException("Alerta '{$input->alertaId}' no encontrada.");
        }

        $alerta->ignorar();
        $this->alertaRepo->guardar($alerta);

        return new IgnorarAlertaOutput(
            alertaId: (string) $alerta->id(),
            estado: $alerta->estado()->valor(),
        );
    }
}
