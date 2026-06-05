<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorVerbatim;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;

final class BuscarLocalidadesPorVerbatimHandler
{
    public function __construct(
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(BuscarLocalidadesPorVerbatimInput $input): BuscarLocalidadesPorVerbatimOutput
    {
        $verbatim = trim($input->verbatim);

        if ($verbatim === '') {
            return new BuscarLocalidadesPorVerbatimOutput(items: [], verbatimConsultado: $verbatim);
        }

        $candidatos = $this->localidadRepo->buscarPorNombreContiene($verbatim);

        $puntuados = array_map(
            fn (Localidad $l) => new BuscarLocalidadesPorVerbatimItem(
                id: (string) $l->id(),
                nombreCanonico: $l->nombreCanonico(),
                rango: $l->rango()->value,
                country: $l->country(),
                stateProvince: $l->stateProvince(),
                puntajeSimilitud: $this->calcularPuntaje($verbatim, $l->nombreCanonico()),
            ),
            $candidatos,
        );

        usort(
            $puntuados,
            fn (BuscarLocalidadesPorVerbatimItem $a, BuscarLocalidadesPorVerbatimItem $b) => $b->puntajeSimilitud <=> $a->puntajeSimilitud,
        );

        $limite = max(1, min(100, $input->limite));

        return new BuscarLocalidadesPorVerbatimOutput(
            items: array_slice($puntuados, 0, $limite),
            verbatimConsultado: $verbatim,
        );
    }

    private function calcularPuntaje(string $verbatim, string $candidato): float
    {
        $verbatim = mb_strtolower($verbatim);
        $candidato = mb_strtolower($candidato);

        similar_text($verbatim, $candidato, $porcentaje);

        return round($porcentaje, 2);
    }
}
