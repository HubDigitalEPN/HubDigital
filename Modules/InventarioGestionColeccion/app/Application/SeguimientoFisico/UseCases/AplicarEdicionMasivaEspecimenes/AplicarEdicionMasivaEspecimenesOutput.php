<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AplicarEdicionMasivaEspecimenes;

final readonly class AplicarEdicionMasivaEspecimenesOutput
{
    /**
     * @param  int  $cambiados  Filas cuyo valor era distinto y se escribió.
     * @param  int  $sinCambio  Filas que ya tenían ese valor: no se tocan ni se
     *                          registran, para que deshacer no las revierta a
     *                          algo que nunca cambió.
     * @param  list<array{codigoCatalogo: string, previo: ?string, nuevo: ?string}>  $muestra
     *                                                                                         Primeras filas afectadas, para la vista previa.
     * @param  ?string  $edicionId  Null cuando fue una simulación.
     */
    public function __construct(
        public int $cambiados,
        public int $sinCambio,
        public array $muestra = [],
        public ?string $edicionId = null,
    ) {}
}
