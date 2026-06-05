<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;

/**
 * Bandeja de revisión: agrupa los `taxon_verbatim` con `taxon_id IS NULL`
 * y propone candidatos del catálogo de taxones por similitud.
 *
 * Estrategia escalable (mismo patrón que localidades P5.1):
 *  - Agrupación SQL-side (GROUP BY) — 1 query.
 *  - Catálogo de taxones cargado en memoria una vez (~4k registros del
 *    import del Excel). Por cada verbatim se computan similitudes en PHP.
 */
final class ListarTaxonVerbatimsPendientesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ListarTaxonVerbatimsPendientesInput $input): ListarTaxonVerbatimsPendientesOutput
    {
        $limiteCandidatos = max(1, min(20, $input->limiteCandidatosPorVerbatim));
        $pagina = max(1, $input->pagina);
        $porPagina = max(1, min(100, $input->porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $grupos = $this->especimenRepo->agruparTaxonVerbatimsPendientes($porPagina, $offset);
        $total = $this->especimenRepo->contarTaxonVerbatimsPendientes();
        $todosTaxones = $this->taxonRepo->buscarTodos();

        $items = [];
        foreach ($grupos as $verbatim => $conteo) {
            $candidatos = $this->candidatosParaVerbatim((string) $verbatim, $todosTaxones, $limiteCandidatos);
            $items[] = new ListarTaxonVerbatimsPendientesItem(
                verbatim: (string) $verbatim,
                totalEspecimenes: $conteo,
                candidatos: $candidatos,
            );
        }

        return new ListarTaxonVerbatimsPendientesOutput(
            items: $items,
            totalVerbatimsDistintos: $total,
            pagina: $pagina,
            porPagina: $porPagina,
        );
    }

    /**
     * @param  Taxon[]  $todosTaxones
     * @return ListarTaxonVerbatimsPendientesCandidato[]
     */
    private function candidatosParaVerbatim(string $verbatim, array $todosTaxones, int $limite): array
    {
        // Token primero (probable género) para boost de matching de verbatims
        // tipo "Acragas_sp.1" → "Acragas".
        $fragmentos = preg_split('/[\s_.]+/', $verbatim) ?: [$verbatim];
        $primero = mb_strtolower(($fragmentos[0] ?? '') !== '' ? $fragmentos[0] : $verbatim);

        $candidatos = [];
        foreach ($todosTaxones as $t) {
            $nombre = $t->nombreCientifico();
            $puntaje = $this->calcularPuntaje($verbatim, $nombre);

            // Boost: si el primer token del verbatim coincide con el inicio del nombre canónico.
            if ($primero !== '' && str_starts_with(mb_strtolower($nombre), $primero)) {
                $puntaje = min(100.0, $puntaje + 15.0);
            }

            $candidatos[] = new ListarTaxonVerbatimsPendientesCandidato(
                taxonId: (string) $t->id(),
                nombreCientifico: $nombre,
                rango: $t->rango()->value,
                puntajeSimilitud: round($puntaje, 2),
            );
        }

        usort(
            $candidatos,
            fn (ListarTaxonVerbatimsPendientesCandidato $a, ListarTaxonVerbatimsPendientesCandidato $b) => $b->puntajeSimilitud <=> $a->puntajeSimilitud,
        );

        return array_slice($candidatos, 0, $limite);
    }

    private function calcularPuntaje(string $verbatim, string $candidato): float
    {
        similar_text(mb_strtolower($verbatim), mb_strtolower($candidato), $porcentaje);

        return (float) $porcentaje;
    }
}
