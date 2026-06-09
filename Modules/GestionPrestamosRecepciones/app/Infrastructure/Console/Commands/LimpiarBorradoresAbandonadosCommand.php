<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

final class LimpiarBorradoresAbandonadosCommand extends Command
{
    protected $signature = 'solicitudes:limpiar-borradores {--dias=30 : Días de inactividad para considerar un borrador como abandonado}';

    protected $description = 'Elimina borradores de solicitudes de depósito abandonados y sus archivos asociados';

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
                // Ambos discos: los borradores antiguos guardaban en 'public'.
                Storage::delete($ruta);
                Storage::disk('public')->delete($ruta);
            }

            $borrador->delete();
            $eliminados++;
        }

        $this->info("Se eliminaron {$eliminados} borrador(es) abandonado(s) con más de {$dias} días de inactividad.");

        return self::SUCCESS;
    }
}
