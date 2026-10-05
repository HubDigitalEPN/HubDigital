<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\DesgloseLocalidad;

/** Índice INEC de cantones y parroquias, reutilizado durante una carga. */
final class CatalogoTerritoriosDesglose
{
    private array $provincias = [];

    public function paraProvincia(string $provincia): array
    {
        $codigo = CatalogoLocalidadesEcuador::codigoProvincia($provincia);
        if ($codigo === null) {
            foreach (CatalogoTerritorialEcuador::provincias() as $referencia) {
                if (DesgloseLocalidad::clave($referencia['nombre']) === DesgloseLocalidad::clave($provincia)) {
                    $codigo = $referencia['codigo'];
                    break;
                }
            }
        }
        if ($codigo === null) return [];
        if (isset($this->provincias[$codigo])) return $this->provincias[$codigo];
        $nombres = [];
        foreach (DB::table(CatalogoLocalidadesEcuador::TABLA)->where('provincia_codigo', $codigo)
            ->where('fuente', 'LIKE', 'INEC %')->distinct()->get(['canton', 'parroquia']) as $fila) {
            foreach ([$fila->canton, $fila->parroquia] as $nombre) {
                if (trim($nombre ?? '') !== '') $nombres[DesgloseLocalidad::clave($nombre)] = $nombre;
            }
        }
        return $this->provincias[$codigo] = $nombres;
    }
}
