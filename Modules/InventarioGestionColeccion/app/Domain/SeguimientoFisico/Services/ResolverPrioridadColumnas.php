<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\ConfiguracionColumnaRepositoryInterface;

/**
 * Resuelve la prioridad efectiva de las columnas de una pantalla:
 *  1) Lee los overrides de BD (taxonomia.configuracion_columnas).
 *  2) Cae al default hardcoded del RegistroColumnas* cuando no hay override.
 *
 * Las pantallas usan este servicio para no mezclar lógica de defaults
 * con persistencia.
 */
final class ResolverPrioridadColumnas
{
    public function __construct(
        private readonly ConfiguracionColumnaRepositoryInterface $repo,
    ) {}

    /**
     * Recibe el array de columnas tal como las devuelve `RegistroColumnas*::todas()`
     * y devuelve el mismo array pero con la `prioridad` reemplazada por el
     * override de BD cuando exista.
     *
     * @param  string  $pantalla  Identificador de la pantalla (`especimenes`, `muestras`, etc.)
     * @param  list<array{clave:string, etiqueta:string, grupo:string, prioridad:string, visiblePorDefecto:bool}>  $columnas
     * @return list<array{clave:string, etiqueta:string, grupo:string, prioridad:string, visiblePorDefecto:bool}>
     */
    public function aplicar(string $pantalla, array $columnas): array
    {
        $overrides = [];
        foreach ($this->repo->listarPorPantalla($pantalla) as $override) {
            $overrides[$override->claveColumna()] = $override->prioridad()->value;
        }

        if ($overrides === []) {
            return $columnas;
        }

        return array_map(
            function (array $c) use ($overrides): array {
                if (isset($overrides[$c['clave']])) {
                    $c['prioridad'] = $overrides[$c['clave']];
                }

                return $c;
            },
            $columnas,
        );
    }
}
