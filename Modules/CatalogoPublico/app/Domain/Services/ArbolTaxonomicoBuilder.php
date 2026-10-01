<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Services;

use Modules\CatalogoPublico\Domain\ValueObjects\ArbolTaxonomico;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;
use Modules\CatalogoPublico\Domain\ValueObjects\NodoEspecie;
use Modules\CatalogoPublico\Domain\ValueObjects\NodoTaxonomico;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final class ArbolTaxonomicoBuilder
{
    /** Rangos intermedios de la jerarquía (excluye Species, que es calculado). */
    private const array RANGOS_INTERMEDIOS = [
        RangoTaxonomico::Phylum,
        RangoTaxonomico::Class_,
        RangoTaxonomico::Order,
        RangoTaxonomico::Family,
        RangoTaxonomico::Genus,
    ];

    /**
     * Construye el árbol taxonómico de divulgación a partir de la lista completa de especímenes.
     *
     * @param  list<EspecimenParaArbol>  $especimenes
     */
    public function construir(array $especimenes): ArbolTaxonomico
    {
        // PASO 1 — Filtrar: solo especímenes divulgables en árbol
        $visibles = array_values(
            array_filter($especimenes, fn (EspecimenParaArbol $e) => $e->esDivulgableEnArbol())
        );

        if ($visibles === []) {
            return ArbolTaxonomico::construir([], [], []);
        }

        // PASO 2 — Nodos intermedios deduplicados (Phylum → Genus)
        /** @var array<string, NodoTaxonomico> clave → NodoTaxonomico */
        $nodosMapa = [];
        $especimenesPorNodo = [];
        $especimenesSinFilo = [];

        foreach ($visibles as $especimen) {
            $padreAnterior = 'root';
            if ($especimen->jerarquia->phylum === '') {
                $especimenesSinFilo[] = $especimen->especimenId;
            }

            foreach (self::RANGOS_INTERMEDIOS as $rango) {
                $taxon = $especimen->jerarquia->valorEnRango($rango);
                if ($taxon === '') {
                    continue;
                }
                $clave = $rango->value.'::'.$taxon;
                if (! isset($nodosMapa[$clave])) {
                    $nodo = NodoTaxonomico::crear($rango, $taxon, $padreAnterior);
                    $nodosMapa[$nodo->clave()] = $nodo;
                }

                $padreAnterior = $taxon;
                $especimenesPorNodo[$rango->value.':'.$taxon][] = $especimen->especimenId;
            }
        }

        // PASO 3 — Nodos especie deduplicados y agrupación de especímenes por especie
        /** @var array<string, NodoEspecie> especie → NodoEspecie */
        $especiesMapa = [];

        /** @var array<string, list<string>> especie → [occurrenceIDs] */
        $especimenesPorEspecie = [];

        foreach ($visibles as $especimen) {
            if ($especimen->jerarquia->scientificName === '') {
                continue;
            }
            $nombre = $especimen->jerarquia->scientificName;
            if (! isset($especiesMapa[$nombre])) {
                $nodoEspecie = NodoEspecie::desdeJerarquia($especimen->jerarquia);
                $especiesMapa[$nodoEspecie->especie] = $nodoEspecie;
            }
            $especimenesPorEspecie[$nombre][] = $especimen->occurrenceID;
            $especimenesPorNodo['species:'.$nombre][] = $especimen->especimenId;
        }

        // Cada nodo procede de un registro visible: conserva los identificados solo
        // hasta filo, clase, orden, familia o género, aunque aún no tengan especie.

        return ArbolTaxonomico::construir(
            nodosJerarquicos: array_values($nodosMapa),
            especies: array_values($especiesMapa),
            especimenesPorEspecie: $especimenesPorEspecie,
            especimenesPorNodo: $especimenesPorNodo,
            especimenIds: array_map(fn (EspecimenParaArbol $e): string => $e->especimenId, $visibles),
            especimenesSinFilo: $especimenesSinFilo,
        );
    }

    /**
     * Elimina nodos intermedios cuyos descendientes visibles han desaparecido.
     *
     * @param  array<string, NodoTaxonomico>  $nodosMapa
     * @param  array<string, NodoEspecie>  $especiesMapa
     * @return array<string, NodoTaxonomico>
     */
    private function podarHuerfanos(array $nodosMapa, array $especiesMapa): array
    {
        // Construir conjunto de nombres de genus con al menos un nodo especie hijo
        $genusConHijos = [];
        foreach ($especiesMapa as $nodoEspecie) {
            $genusConHijos[$nodoEspecie->padreGenus] = true;
        }

        // Podar Genus sin especies hijas
        foreach ($nodosMapa as $clave => $nodo) {
            if ($nodo->rango === RangoTaxonomico::Genus && ! isset($genusConHijos[$nodo->taxon])) {
                unset($nodosMapa[$clave]);
            }
        }

        // Poda bottom-up: pares [padre, hijo] en orden de más específico a más general.
        // Se usa lista de pares en lugar de mapa asociativo para evitar usar enum cases
        // como claves de array (PHP solo acepta int|string como claves).
        $pares = [
            [RangoTaxonomico::Family, RangoTaxonomico::Genus],
            [RangoTaxonomico::Order,  RangoTaxonomico::Family],
            [RangoTaxonomico::Class_, RangoTaxonomico::Order],
            [RangoTaxonomico::Phylum, RangoTaxonomico::Class_],
        ];

        foreach ($pares as [$rangoPadre, $rangoHijo]) {
            $hijosVivos = [];
            foreach ($nodosMapa as $nodo) {
                if ($nodo->rango === $rangoHijo) {
                    $hijosVivos[$nodo->nombrePadre] = true;
                }
            }

            foreach ($nodosMapa as $clave => $nodo) {
                if ($nodo->rango === $rangoPadre && ! isset($hijosVivos[$nodo->taxon])) {
                    unset($nodosMapa[$clave]);
                }
            }
        }

        return $nodosMapa;
    }
}
