<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\EstadoEspecimenCatalogoPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenesEnPrestamo\MarcarEspecimenesEnPrestamoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenesEnPrestamo\MarcarEspecimenesEnPrestamoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RestaurarEstadoEspecimenes\RestaurarEstadoEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RestaurarEstadoEspecimenes\RestaurarEstadoEspecimenesInput;

/**
 * ACL — Anti-Corruption Layer de escritura entre GestionPrestamosRecepciones (Customer)
 * e InventarioGestionColeccion (Supplier).
 *
 * Asimetría deliberada respecto a {@see InventarioGestionColeccionEspecimenesAdapter}:
 * ese lee `taxonomia.*` con SQL crudo, porque para *consultar* la base de datos basta
 * como punto de contacto. Para *escribir* no alcanza — un UPDATE desde aquí se llevaría
 * la regla de transición del estado fuera del módulo que es dueño del dato. Por eso este
 * adaptador delega en los casos de uso del inventario en lugar de tocar sus tablas.
 *
 * La regla que se respeta en ambos sentidos: este módulo puede depender de la capa
 * Application del inventario, nunca de su Domain.
 */
final class InventarioGestionColeccionEstadoEspecimenAdapter implements EstadoEspecimenCatalogoPort
{
    public function __construct(
        private readonly MarcarEspecimenesEnPrestamoHandler $marcarHandler,
        private readonly RestaurarEstadoEspecimenesHandler $restaurarHandler,
    ) {}

    public function marcarEnPrestamo(array $especimenIds): void
    {
        if ($especimenIds === []) {
            return;
        }

        $resultado = $this->marcarHandler->handle(
            new MarcarEspecimenesEnPrestamoInput(especimenIds: $especimenIds)
        );

        if ($resultado->tieneAnomalias()) {
            Log::warning('Sincronización de préstamo: especímenes no marcados en préstamo.', [
                'no_encontrados' => $resultado->noEncontrados,
                'ya_en_prestamo' => $resultado->yaEnPrestamo,
            ]);
        }
    }

    public function restaurar(array $especimenIds, array $conNovedad): void
    {
        if ($especimenIds === []) {
            return;
        }

        $resultado = $this->restaurarHandler->handle(
            new RestaurarEstadoEspecimenesInput(
                especimenIds: $especimenIds,
                conNovedad: $conNovedad,
            )
        );

        if ($resultado->tieneAnomalias()) {
            Log::warning('Sincronización de cierre: especímenes no restaurados en el catálogo.', [
                'no_encontrados' => $resultado->noEncontrados,
            ]);
        }
    }
}
