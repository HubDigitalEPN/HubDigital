<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Services;

use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;
use Modules\CatalogoPublico\Domain\ValueObjects\EstadisticaTaxon;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final readonly class CalculadorEstadisticasTaxonomicas
{
    public function __construct(
        private ProveedorEspecimenesParaArbolPort $proveedorArbol,
    ) {}

    /** @return list<EstadisticaTaxon> */
    public function topPorRango(RangoTaxonomico $rango, int $limite): array
    {
        if ($limite < 1) {
            return [];
        }

        $conteo = $this->conteoPorTaxon($rango);

        arsort($conteo);

        $top = array_slice($conteo, 0, $limite, true);

        $estadisticas = [];
        foreach ($top as $nombre => $cantidad) {
            $estadisticas[] = EstadisticaTaxon::crear($rango, (string) $nombre, $cantidad);
        }

        return $estadisticas;
    }

    public function conteoTotalTaxonesUnicos(RangoTaxonomico $rango): int
    {
        return count($this->conteoPorTaxon($rango));
    }

    /** @return array<string, int> */
    private function conteoPorTaxon(RangoTaxonomico $rango): array
    {
        $divulgables = array_filter(
            $this->proveedorArbol->obtenerTodos(),
            static fn (EspecimenParaArbol $e): bool => $e->esDivulgableEnArbol(),
        );

        $conteo = [];

        foreach ($divulgables as $especimen) {
            $nombre = $this->nombreEnRango($especimen, $rango);

            if ($nombre === null) {
                continue;
            }

            $conteo[$nombre] = ($conteo[$nombre] ?? 0) + 1;
        }

        return $conteo;
    }

    private function nombreEnRango(EspecimenParaArbol $especimen, RangoTaxonomico $rango): ?string
    {
        if ($rango === RangoTaxonomico::Species) {
            return $especimen->jerarquia->scientificName;
        }

        $valor = $especimen->jerarquia->valorEnRango($rango);

        return trim($valor) === '' ? null : $valor;
    }
}
