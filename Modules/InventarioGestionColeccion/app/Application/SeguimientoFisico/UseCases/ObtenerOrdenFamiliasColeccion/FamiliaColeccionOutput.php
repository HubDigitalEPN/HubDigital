<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerOrdenFamiliasColeccion;

final readonly class FamiliaColeccionOutput
{
    /**
     * @param  string[]  $subfamilias  subfamilias presentes en la colección bajo esta familia (alfabético)
     */
    public function __construct(
        public string $familia,
        public array $subfamilias,
        public bool $enSecuencia,
        public bool $presente,
    ) {}
}
