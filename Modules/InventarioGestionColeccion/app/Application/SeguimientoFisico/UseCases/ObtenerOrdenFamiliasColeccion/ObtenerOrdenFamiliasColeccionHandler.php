<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerOrdenFamiliasColeccion;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;

/**
 * Arma la vista del orden de familias de la colección:
 * primero las familias en la secuencia esperada del curador (en su orden), luego las
 * familias presentes en cajas pero aún no secuenciadas (alfabético). Para cada familia
 * lista las subfamilias presentes como referencia visual (orden alfabético dentro del bloque).
 */
final class ObtenerOrdenFamiliasColeccionHandler
{
    public function __construct(
        private readonly OrdenEsperadoFamiliasRepository $ordenFamiliasRepo,
        private readonly CajaRepository $cajaRepo,
    ) {}

    public function handle(): ObtenerOrdenFamiliasColeccionOutput
    {
        $orden = $this->ordenFamiliasRepo->obtener();

        // familia normalizada (minúsculas) => ['familia' => nombre original, 'subfamilias' => set]
        $presentes = [];

        foreach ($this->cajaRepo->buscarTodas() as $caja) {
            $clasificacion = $caja->clasificacionTaxonomica();
            $familia = $clasificacion?->familia();

            if ($familia === null) {
                continue;
            }

            $clave = mb_strtolower($familia);
            if (! isset($presentes[$clave])) {
                $presentes[$clave] = ['familia' => $familia, 'subfamilias' => []];
            }

            $subfamilia = $clasificacion->subfamilia();
            if ($subfamilia !== null) {
                $presentes[$clave]['subfamilias'][mb_strtolower($subfamilia)] = $subfamilia;
            }
        }

        $items = [];
        $yaIncluidas = [];

        // 1. Familias en la secuencia esperada, en orden.
        foreach ($orden->familias() as $familia) {
            $clave = mb_strtolower($familia);
            $yaIncluidas[$clave] = true;
            $items[] = $this->construirItem(
                familia: $familia,
                datosPresente: $presentes[$clave] ?? null,
                enSecuencia: true,
            );
        }

        // 2. Familias presentes pero no secuenciadas, alfabético.
        $noSecuenciadas = array_filter(
            $presentes,
            static fn (array $_d, string $clave) => ! isset($yaIncluidas[$clave]),
            ARRAY_FILTER_USE_BOTH,
        );
        usort(
            $noSecuenciadas,
            static fn (array $a, array $b): int => strcasecmp($a['familia'], $b['familia']),
        );

        foreach ($noSecuenciadas as $datos) {
            $items[] = $this->construirItem(
                familia: $datos['familia'],
                datosPresente: $datos,
                enSecuencia: false,
            );
        }

        return new ObtenerOrdenFamiliasColeccionOutput($items);
    }

    /**
     * @param  array{familia: string, subfamilias: array<string, string>}|null  $datosPresente
     */
    private function construirItem(string $familia, ?array $datosPresente, bool $enSecuencia): FamiliaColeccionOutput
    {
        $subfamilias = $datosPresente !== null ? array_values($datosPresente['subfamilias']) : [];
        usort($subfamilias, 'strcasecmp');

        return new FamiliaColeccionOutput(
            familia: $familia,
            subfamilias: $subfamilias,
            enSecuencia: $enSecuencia,
            presente: $datosPresente !== null,
        );
    }
}
