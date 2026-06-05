<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResumirEstadoRevision;

final readonly class ResumirEstadoRevisionOutput
{
    public function __construct(
        public int $totalEspecimenes,
        public int $publicablesGbif,
        public int $pendientesRevision,
        public int $localidadesVerbatimPendientes,
        public int $taxonVerbatimPendientes,
        public int $duplicadosCatalogNumberGrupos,
        public int $fechaVerbatimPendientes,
        public int $muestrasPendientes,
    ) {}

    public function porcentajePublicables(): float
    {
        return $this->totalEspecimenes === 0
            ? 0.0
            : round(($this->publicablesGbif / $this->totalEspecimenes) * 100, 1);
    }
}
