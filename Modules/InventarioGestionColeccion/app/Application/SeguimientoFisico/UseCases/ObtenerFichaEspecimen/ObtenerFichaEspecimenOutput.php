<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen;

final readonly class ObtenerFichaEspecimenOutput
{
    /**
     * @param  array<string, mixed>  $ficha  Mapa plano con TODAS las columnas del
     *                                       espécimen (claves de RegistroColumnasEspecimen).
     *                                       Vacío si el espécimen no existe.
     */
    public function __construct(
        public array $ficha,
        public bool $encontrado,
    ) {}
}
