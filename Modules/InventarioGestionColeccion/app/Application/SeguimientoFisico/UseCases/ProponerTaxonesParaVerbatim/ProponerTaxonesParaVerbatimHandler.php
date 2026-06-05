<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerTaxonesParaVerbatim;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;

/**
 * Sugiere taxones candidatos para un `taxon_verbatim` crudo del Excel.
 *
 * Estrategia:
 *  - Si el verbatim incluye múltiples palabras (binomen), buscamos por la primera
 *    (probable género) y luego refinamos con similar_text sobre el binomen completo.
 *  - Si es una sola palabra, buscamos por substring directo.
 *  - El ordenamiento final es por puntaje de similitud descendente.
 */
final class ProponerTaxonesParaVerbatimHandler
{
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepo,
    ) {}

    public function handle(ProponerTaxonesParaVerbatimInput $input): ProponerTaxonesParaVerbatimOutput
    {
        $verbatim = trim($input->verbatim);
        if ($verbatim === '') {
            return new ProponerTaxonesParaVerbatimOutput(items: [], verbatimConsultado: $verbatim);
        }

        // Split por whitespace, underscore o punto para extraer el primer token
        // (típicamente el género en verbatims provisionales tipo "Acragas_sp.1").
        $fragmentos = preg_split('/[\s_.]+/', $verbatim) ?: [$verbatim];
        $primero = ($fragmentos[0] ?? '') !== '' ? $fragmentos[0] : $verbatim;

        $candidatos = $this->taxonRepo->buscarPorNombreContiene($primero);

        $puntuados = array_map(
            fn (Taxon $t) => new ProponerTaxonesParaVerbatimItem(
                id: (string) $t->id(),
                nombreCientifico: $t->nombreCientifico(),
                rango: $t->rango()->value,
                puntajeSimilitud: $this->calcularPuntaje($verbatim, $t->nombreCientifico()),
            ),
            $candidatos,
        );

        usort(
            $puntuados,
            fn (ProponerTaxonesParaVerbatimItem $a, ProponerTaxonesParaVerbatimItem $b) => $b->puntajeSimilitud <=> $a->puntajeSimilitud,
        );

        $limite = max(1, min(100, $input->limite));

        return new ProponerTaxonesParaVerbatimOutput(
            items: array_slice($puntuados, 0, $limite),
            verbatimConsultado: $verbatim,
        );
    }

    private function calcularPuntaje(string $verbatim, string $candidato): float
    {
        $a = mb_strtolower($verbatim);
        $b = mb_strtolower($candidato);
        similar_text($a, $b, $porcentaje);

        return round($porcentaje, 2);
    }
}
