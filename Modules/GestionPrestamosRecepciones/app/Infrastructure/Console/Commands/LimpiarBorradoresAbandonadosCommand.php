<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Comando Artisan para limpiar borradores de solicitudes de depósito antiguos.
 *
 * Elimina las solicitudes que han permanecido en estado 'EnBorrador' por más
 * de un número determinado de días, borrando también sus archivos asociados en storage.
 */
final class LimpiarBorradoresAbandonadosCommand extends Command
{
    protected $signature = 'solicitudes:limpiar-borradores {--dias=30 : Días de inactividad para considerar un borrador como abandonado}';

    protected $description = 'Elimina borradores de solicitudes de depósito abandonados y sus archivos asociados';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $dias = (int) $this->option('dias');

        $borradores = SolicitudDepositoEloquentModel::where('estado', EstadoSolicitudDeposito::EnBorrador->value)
            ->where('updated_at', '<', now()->subDays($dias))
            ->get();

        if ($borradores->isEmpty()) {
            $this->info('No se encontraron borradores abandonados.');

            return self::SUCCESS;
        }

        $eliminados = 0;

        foreach ($borradores as $borrador) {
            $documentos = $borrador->documentos_cargados ?? [];
            foreach ($documentos as $ruta) {
                Storage::disk('public')->delete($ruta);
            }

            $borrador->delete();
            $eliminados++;
        }

        $this->info("Se eliminaron {$eliminados} borrador(es) abandonado(s) con más de {$dias} días de inactividad.");

        return self::SUCCESS;
    }
}
