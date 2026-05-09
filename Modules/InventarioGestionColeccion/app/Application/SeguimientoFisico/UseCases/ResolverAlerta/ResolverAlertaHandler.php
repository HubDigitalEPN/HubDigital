<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;

final class ResolverAlertaHandler
{
    public function __construct(
        private readonly AlertaUbicacionRepository $alertaRepo,
    ) {}

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
