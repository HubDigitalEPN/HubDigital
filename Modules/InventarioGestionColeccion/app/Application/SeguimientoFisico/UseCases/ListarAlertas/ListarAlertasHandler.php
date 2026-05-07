<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;

final class ListarAlertasHandler
{
    public function __construct(
        private readonly AlertaUbicacionRepository $alertaRepo,
    ) {}

    public function handle(ListarAlertasInput $input): ListarAlertasOutput
    {
        $estado = $input->estado !== null ? EstadoAlerta::from($input->estado) : null;

        $alertas = $this->alertaRepo->buscarTodas($estado);

        $items = array_map(
            fn ($a) => new ListarAlertasItemOutput(
                id: (string) $a->id(),
                cajaId: (string) $a->cajaId(),
                tipo: $a->tipo()->valor(),
                estado: $a->estado()->valor(),
                datosContexto: $a->datosContexto(),
            ),
            $alertas,
        );

        return new ListarAlertasOutput($items);
    }
}
