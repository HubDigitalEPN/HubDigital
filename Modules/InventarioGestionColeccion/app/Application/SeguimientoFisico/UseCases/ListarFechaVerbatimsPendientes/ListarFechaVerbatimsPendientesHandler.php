<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarFechaVerbatimsPendientes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ParsearFechaVerbatim;

/**
 * Bandeja de parseo masivo de fechas (spec P5.5):
 *
 *  - Lista verbatim distintos cuyo fecha_colecta sigue siendo null (el
 *    importador no pudo parsearlos).
 *  - Por cada verbatim intenta sugerir un parseo con el servicio de
 *    dominio ParsearFechaVerbatim (que mejoró desde el hardening: ahora
 *    soporta rangos). Si no logra parsear, sugerencia = null y el
 *    curador escribe la fecha manualmente.
 */
final class ListarFechaVerbatimsPendientesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ListarFechaVerbatimsPendientesInput $input): ListarFechaVerbatimsPendientesOutput
    {
        $pagina = max(1, $input->pagina);
        $porPagina = max(1, min(100, $input->porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $grupos = $this->especimenRepo->agruparFechaVerbatimsPendientes($porPagina, $offset);
        $total = $this->especimenRepo->contarFechaVerbatimsPendientes();

        $items = [];
        foreach ($grupos as $verbatim => $conteo) {
            // Pivot 25 — consistente con el importer (decisión P0.1).
            [$inicio, $fin] = ParsearFechaVerbatim::parsearRango((string) $verbatim, 25);

            $items[] = new ListarFechaVerbatimsPendientesItem(
                verbatim: (string) $verbatim,
                totalEspecimenes: $conteo,
                sugerenciaInicio: $inicio?->format('Y-m-d'),
                sugerenciaFin: $fin?->format('Y-m-d'),
            );
        }

        return new ListarFechaVerbatimsPendientesOutput(
            items: $items,
            totalVerbatimsDistintos: $total,
            pagina: $pagina,
            porPagina: $porPagina,
        );
    }
}
