<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DeshacerEdicionMasiva;

final readonly class DeshacerEdicionMasivaOutput
{
    /**
     * @param  int  $revertidos  Filas devueltas a su valor previo.
     * @param  int  $conflictos  Filas cuyo campo cambió DESPUÉS de la edición.
     *                           No se tocan: revertirlas destruiría un cambio
     *                           más reciente que nadie pidió descartar.
     * @param  int  $desaparecidos  Especímenes que ya no existen.
     */
    public function __construct(
        public int $revertidos,
        public int $conflictos = 0,
        public int $desaparecidos = 0,
    ) {}
}
