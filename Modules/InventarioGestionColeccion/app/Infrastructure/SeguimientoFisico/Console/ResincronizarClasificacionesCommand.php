<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console;

use Illuminate\Console\Command;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResincronizarClasificaciones\ResincronizarClasificacionesHandler;

/**
 * Fallback de mantenimiento: recalcula la clasificación taxonómica de todos los unit trays
 * y cajas a partir de los especímenes realmente presentes, limpiando las clasificaciones
 * cacheadas que quedaron huérfanas (especímenes borrados de la BD, trays movidos, etc.).
 *
 *   php artisan inventario:resincronizar-clasificaciones
 */
final class ResincronizarClasificacionesCommand extends Command
{
    protected $signature = 'inventario:resincronizar-clasificaciones';

    protected $description = 'Recalcula la clasificación taxonómica de trays y cajas desde sus especímenes actuales, limpiando clasificaciones huérfanas.';

    public function handle(ResincronizarClasificacionesHandler $handler): int
    {
        $this->info('Resincronizando clasificaciones de unit trays y cajas...');

        try {
            $output = $handler->handle();
        } catch (\Throwable $e) {
            $this->error('Falló la resincronización: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Unit trays recalculados: {$output->traysRecalculados} ({$output->traysSinClasificacion} quedaron sin clasificación).");
        $this->info("Cajas recalculadas: {$output->cajasRecalculadas} ({$output->cajasSinClasificacion} quedaron sin clasificación).");

        return self::SUCCESS;
    }
}
