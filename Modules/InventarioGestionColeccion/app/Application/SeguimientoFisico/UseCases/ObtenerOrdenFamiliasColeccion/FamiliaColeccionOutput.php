<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerOrdenFamiliasColeccion;

/**
 * DTO de salida con una familia del orden de la colección: sus subfamilias presentes y si está
 * en la secuencia esperada y/o presente en alguna caja.
 */
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
