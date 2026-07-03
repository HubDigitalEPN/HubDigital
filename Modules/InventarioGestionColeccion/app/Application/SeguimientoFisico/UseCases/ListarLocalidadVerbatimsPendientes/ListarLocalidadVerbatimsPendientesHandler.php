<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;

/**
 * Bandeja de revisión: agrupa los `localidad_verbatim` con `localidad_id` null
 * y propone candidatos canónicos por similitud.
 *
 * Estrategia escalable:
 *  - El agrupamiento se hace SQL-side (GROUP BY + COUNT) → 1 query para
 *    todas las 48k filas.
 *  - Las localidades canónicas son pocas (cientos), se cargan en memoria
 *    una vez y se computa similar_text() en PHP.
 */
final class ListarLocalidadVerbatimsPendientesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(ListarLocalidadVerbatimsPendientesInput $input): ListarLocalidadVerbatimsPendientesOutput
    {
        $limiteCandidatos = max(1, min(20, $input->limiteCandidatosPorVerbatim));
        $pagina = max(1, $input->pagina);
        $porPagina = max(1, min(100, $input->porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $grupos = $this->especimenRepo->agruparLocalidadVerbatimsPendientes($porPagina, $offset);
        $total = $this->especimenRepo->contarLocalidadVerbatimsPendientes();
        $todasLocalidades = $this->localidadRepo->buscarTodos();

        $items = [];
        foreach ($grupos as $verbatim => $conteo) {
            $candidatos = $this->candidatosParaVerbatim((string) $verbatim, $todasLocalidades, $limiteCandidatos);
            $items[] = new ListarLocalidadVerbatimsPendientesItem(
                verbatim: (string) $verbatim,
                totalEspecimenes: $conteo,
                candidatos: $candidatos,
                esNombreUnico: ! str_contains((string) $verbatim, ','),
            );
        }

        return new ListarLocalidadVerbatimsPendientesOutput(
            items: $items,
            totalVerbatimsDistintos: $total,
            pagina: $pagina,
            porPagina: $porPagina,
        );
    }

    /**
     * @param  Localidad[]  $todasLocalidades
     * @return ListarLocalidadVerbatimsPendientesCandidato[]
     */
    private function candidatosParaVerbatim(string $verbatim, array $todasLocalidades, int $limite): array
    {
        $candidatos = array_map(
            fn (Localidad $l) => new ListarLocalidadVerbatimsPendientesCandidato(
                localidadId: (string) $l->id(),
                nombreCanonico: $l->nombreCanonico(),
                rango: $l->rango()->value,
                puntajeSimilitud: $this->calcularPuntaje($verbatim, $l->nombreCanonico()),
            ),
            $todasLocalidades,
        );

        usort(
            $candidatos,
            fn (ListarLocalidadVerbatimsPendientesCandidato $a, ListarLocalidadVerbatimsPendientesCandidato $b) => $b->puntajeSimilitud <=> $a->puntajeSimilitud,
        );

        return array_slice($candidatos, 0, $limite);
    }

    private function calcularPuntaje(string $verbatim, string $candidato): float
    {
        similar_text(mb_strtolower($verbatim), mb_strtolower($candidato), $porcentaje);

        return round($porcentaje, 2);
    }
}
