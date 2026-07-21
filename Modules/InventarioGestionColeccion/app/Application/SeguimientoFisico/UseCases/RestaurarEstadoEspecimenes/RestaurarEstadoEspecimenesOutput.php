<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RestaurarEstadoEspecimenes;

/**
 * Resultado de la restauración por lote al cerrar un préstamo.
 *
 * `noEncontrados` es una anomalía reportada, no un fallo: el caso de uso nunca aborta
 * el lote por ella.
 */
final readonly class RestaurarEstadoEspecimenesOutput
{
    /**
     * @param  string[]  $disponibles  Especímenes que volvieron sin novedad.
     * @param  string[]  $observados  Especímenes que volvieron con observación.
     * @param  string[]  $noEncontrados  IDs sin espécimen en el catálogo.
     */
    public function __construct(
        public array $disponibles,
        public array $observados,
        public array $noEncontrados,
    ) {}

    public function tieneAnomalias(): bool
    {
        return $this->noEncontrados !== [];
    }
}
