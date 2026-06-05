<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarPrioridadColumna;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\ConfiguracionColumna;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\ConfiguracionColumnaRepositoryInterface;

final class ActualizarPrioridadColumnaHandler
{
    public function __construct(
        private readonly ConfiguracionColumnaRepositoryInterface $repo,
    ) {}

    public function handle(ActualizarPrioridadColumnaInput $input): ActualizarPrioridadColumnaOutput
    {
        $existente = $this->repo->buscarPorPantallaYClave($input->pantalla, $input->claveColumna);

        if ($existente === null) {
            $config = ConfiguracionColumna::crear(
                id: $this->repo->nextIdentity(),
                pantalla: $input->pantalla,
                claveColumna: $input->claveColumna,
                prioridad: $input->prioridad,
                actualizadoPorId: $input->actualizadoPorId,
            );
        } else {
            $existente->cambiarPrioridad($input->prioridad, $input->actualizadoPorId);
            $config = $existente;
        }

        $this->repo->guardar($config);

        return new ActualizarPrioridadColumnaOutput(
            pantalla: $config->pantalla(),
            claveColumna: $config->claveColumna(),
            prioridad: $config->prioridad()->value,
        );
    }
}
