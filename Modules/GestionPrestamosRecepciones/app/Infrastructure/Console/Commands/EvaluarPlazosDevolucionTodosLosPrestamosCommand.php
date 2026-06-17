<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo\EvaluarPlazoDevolucionPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo\EvaluarPlazoDevolucionPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;

/**
 * Comando Artisan para evaluar los plazos de devolución de todos los préstamos activos.
 *
 * Itera sobre los préstamos en estado 'Activo' y ejecuta el caso de uso de evaluación
 * para determinar si corresponde enviar recordatorios o marcar como vencido.
 */
final class EvaluarPlazosDevolucionTodosLosPrestamosCommand extends Command
{
    protected $signature = 'prestamos:evaluar-plazos-devolucion';

    protected $description = 'Evalúa el plazo de devolución de todos los préstamos activos y envía recordatorios si corresponde';

    /**
     * Ejecuta el comando.
     */
    public function handle(
        PrestamoRepositoryInterface $prestamoRepo,
        EvaluarPlazoDevolucionPrestamoHandler $handler,
    ): int {
        $prestamos = $prestamoRepo->listarActivos();

        foreach ($prestamos as $prestamo) {
            $handler->handle(new EvaluarPlazoDevolucionPrestamoInput(
                prestamoId: (string) $prestamo->id(),
            ));
        }

        $this->info('Evaluados: '.count($prestamos).' préstamos activos.');

        return self::SUCCESS;
    }
}
