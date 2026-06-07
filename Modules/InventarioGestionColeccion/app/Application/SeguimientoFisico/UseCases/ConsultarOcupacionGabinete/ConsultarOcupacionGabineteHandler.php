<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

/**
 * Resuelve, para cada ranura del gabinete, la caja que la ocupa y su clasificación.
 * Alimenta el nivel "gabinete" del mapa (ranuras como rectángulos coloreados por taxonomía).
 */
final class ConsultarOcupacionGabineteHandler
{
    public function __construct(
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly CajaRepository $cajaRepo,
    ) {}

    public function handle(ConsultarOcupacionGabineteInput $input): ConsultarOcupacionGabineteOutput
    {
        $gabineteId = GabineteId::desde($input->gabineteId);
        $ranuras = $this->ranuraRepo->buscarPorGabinete($gabineteId);

        // El orden físico de la ranura define la lectura del mapa; no dependemos de
        // que el repositorio lo garantice.
        usort($ranuras, fn ($a, $b) => $a->numeroRanura() <=> $b->numeroRanura());

        $items = array_map(function ($ranura): ConsultarOcupacionGabineteItemOutput {
            $cajaId = $ranura->cajaActualId();
            $caja = $cajaId !== null ? $this->cajaRepo->buscarPorId($cajaId) : null;

            return new ConsultarOcupacionGabineteItemOutput(
                ranuraId: (string) $ranura->id(),
                numeroRanura: $ranura->numeroRanura(),
                ocupada: $caja !== null,
                cajaId: $caja !== null ? (string) $caja->id() : null,
                codigoCaja: $caja !== null ? (string) $caja->codigo() : null,
                estado: $caja !== null ? $caja->estadoActual()->valor() : null,
                esEspecial: $caja !== null && $caja->esEspecial(),
                clasificacion: $caja !== null ? $this->clasificacionAArray($caja->clasificacionTaxonomica()) : null,
            );
        }, $ranuras);

        return new ConsultarOcupacionGabineteOutput(items: $items);
    }

    /** @return ?array<string, ?string> */
    private function clasificacionAArray(?ClasificacionTaxonomica $clasificacion): ?array
    {
        if ($clasificacion === null || $clasificacion->estaVacia()) {
            return null;
        }

        return [
            'orden' => $clasificacion->orden(),
            'suborden' => $clasificacion->suborden(),
            'superfamilia' => $clasificacion->superfamilia(),
            'familia' => $clasificacion->familia(),
            'subfamilia' => $clasificacion->subfamilia(),
            'genero' => $clasificacion->genero(),
            'especie' => $clasificacion->especie(),
        ];
    }
}
