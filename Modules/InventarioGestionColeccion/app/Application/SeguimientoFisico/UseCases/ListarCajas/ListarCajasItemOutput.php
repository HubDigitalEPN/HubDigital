<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas;

/**
 * Read model de una Caja para los listados. Incluye la clasificación taxonómica
 * propagada desde sus UnitTrays, que identifica a la caja mejor que su código numérico.
 */
final readonly class ListarCajasItemOutput
{
    /**
     * @param  string[]  $subfamilias  Subfamilias distintas presentes en la caja (dominante primero).
     * @param  string[]  $generos  Géneros distintos presentes en la caja (dominante primero).
     */
    public function __construct(
        public string $id,
        public string $codigo,
        public string $codigoRfid,
        public bool $esEspecial,
        public ?string $observacion,
        public ?string $nombre,
        public string $estado,
        public ?string $orden = null,
        public ?string $suborden = null,
        public ?string $superfamilia = null,
        public ?string $familia = null,
        public ?string $subfamilia = null,
        public ?string $genero = null,
        public ?string $especie = null,
        public array $subfamilias = [],
        public array $generos = [],
    ) {}
}
