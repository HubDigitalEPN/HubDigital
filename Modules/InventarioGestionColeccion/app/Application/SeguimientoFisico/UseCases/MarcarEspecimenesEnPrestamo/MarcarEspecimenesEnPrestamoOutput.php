<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenesEnPrestamo;

/**
 * Resultado del marcado por lote.
 *
 * `noEncontrados` y `yaEnPrestamo` son anomalías reportadas, no fallos: el caso de
 * uso nunca aborta el lote por ellas. Quien lo invoca decide si loguear o alertar.
 */
final readonly class MarcarEspecimenesEnPrestamoOutput
{
    /**
     * @param  string[]  $actualizados  Especímenes que pasaron a `en_prestamo` en esta ejecución.
     * @param  string[]  $noEncontrados  IDs sin espécimen en el catálogo.
     * @param  string[]  $yaEnPrestamo  Especímenes que ya estaban `en_prestamo` antes de la llamada.
     */
    public function __construct(
        public array $actualizados,
        public array $noEncontrados,
        public array $yaEnPrestamo,
    ) {}

    public function tieneAnomalias(): bool
    {
        return $this->noEncontrados !== [] || $this->yaEnPrestamo !== [];
    }
}
