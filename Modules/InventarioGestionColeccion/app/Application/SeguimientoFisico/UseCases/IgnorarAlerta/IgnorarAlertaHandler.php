<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IgnorarAlerta;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;

final class IgnorarAlertaHandler
{
    public function __construct(
        private readonly AlertaUbicacionRepository $alertaRepo,
    ) {}

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
