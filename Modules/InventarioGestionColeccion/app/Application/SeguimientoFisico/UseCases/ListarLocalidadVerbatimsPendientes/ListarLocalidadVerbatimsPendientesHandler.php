<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;

/**
 * Bandeja de revisión: agrupa los especímenes que tienen `localidad_verbatim`
 * sin `localidad_id` enlazada, y propone candidatos canónicos por similitud.
 *
 * NOTA de rendimiento: usa `buscarTodos()` — O(N) en memoria. Suficiente para
 * miles de registros, no para los 48.856 del Excel. La versión escalable
 * usa SQL agrupado y se hará en P6 cuando el importador esté en producción.
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

        $todosEspecimenes = $this->especimenRepo->buscarTodos();
        $todasLocalidades = $this->localidadRepo->buscarTodos();

        $grupos = [];
        foreach ($todosEspecimenes as $especimen) {
            if ($especimen->localidadId() !== null) {
                continue;
            }
            $verbatim = $especimen->localidadVerbatim();
            if ($verbatim === null || $verbatim === '') {
                continue;
            }
            $grupos[$verbatim] = ($grupos[$verbatim] ?? 0) + 1;
        }

        // Ordenar por frecuencia descendente — los verbatims más comunes primero.
        arsort($grupos);

        $items = [];
        foreach ($grupos as $verbatim => $total) {
            $candidatos = $this->candidatosParaVerbatim($verbatim, $todasLocalidades, $limiteCandidatos);
            $items[] = new ListarLocalidadVerbatimsPendientesItem(
                verbatim: (string) $verbatim,
                totalEspecimenes: $total,
                candidatos: $candidatos,
            );
        }

        return new ListarLocalidadVerbatimsPendientesOutput(
            items: $items,
            totalVerbatimsDistintos: count($grupos),
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
