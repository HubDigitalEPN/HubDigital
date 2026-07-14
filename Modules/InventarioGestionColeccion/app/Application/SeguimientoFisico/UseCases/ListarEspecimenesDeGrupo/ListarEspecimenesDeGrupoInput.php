<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo;

final readonly class ListarEspecimenesDeGrupoInput
{
    public const TIPO_FECHA = 'fecha';

    public const TIPO_TAXON = 'taxon';

    public const TIPO_LOCALIDAD = 'localidad';

    public function __construct(
        public string $tipo,
        public string $verbatim,
        public int $limite = 500,
    ) {}
}
