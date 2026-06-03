<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas;

/**
 * Read model de una Caja para los listados. Incluye la clasificación taxonómica
 * propagada desde sus UnitTrays, que identifica a la caja mejor que su código numérico.
 */
final readonly class ListarCajasItemOutput
{
    public function __construct(
        public string $id,
        public string $codigo,
        public string $codigoRfid,
        public bool $esEspecial,
        public ?string $observacion,
        public ?string $nombre,
        public string $estado,
        public ?string $subfamilia = null,
        public ?string $genero = null,
        public ?string $especie = null,
    ) {}
}
