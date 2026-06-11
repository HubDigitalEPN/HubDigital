<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;

/**
 * Caso de uso: listar las alertas de ubicación, opcionalmente filtradas por su estado.
 *
 * @see ListarAlertasInput
 * @see ListarAlertasOutput
 */
final class ListarAlertasHandler
{
    /**
     * @param  AlertaUbicacionRepository  $alertaRepo  Recupera las alertas de ubicación.
     */
    public function __construct(
        private readonly AlertaUbicacionRepository $alertaRepo,
    ) {}

    /**
     * Recupera las alertas (filtrando por estado si se indica) y las proyecta a items de salida.
     */
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
                generadaEn: $a->generadaEn(),
            ),
            $alertas,
        );

        return new ListarAlertasOutput($items);
    }
}
