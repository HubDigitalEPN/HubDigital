<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Backfill del vínculo fila de matriz ↔ espécimen en la colección.
 *
 * El material que entró antes de que la junta existiera solo tiene el `codigo_catalogo`
 * derivado. De ahí sale el índice de fila, pero no el uuid del registro que la declaró:
 * eso hay que reconstruirlo recorriendo la matriz en el mismo orden que usó el ingreso.
 *
 * Ese orden NO está garantizado (la relación de Eloquent no declara `orderBy`), así que
 * el caso de uso del inventario lo verifica comparando el nombre científico de cada fila
 * con el `taxon_verbatim` del espécimen que quedó en esa posición. Si algo no cuadra,
 * esa solicitud se salta entera y se reporta.
 *
 * Por eso corre en seco por defecto: hay que ver el informe antes de escribir.
 *
 *   php artisan deposito:vincular-especimenes            # simulación
 *   php artisan deposito:vincular-especimenes --aplicar  # escribe
 */
final class VincularEspecimenesDepositoCommand extends Command
{
    protected $signature = 'deposito:vincular-especimenes
        {--aplicar : Escribe los vínculos. Sin esta opción solo informa qué haría.}
        {--solicitud= : Limita el backfill a una solicitud concreta (su número, ej. MEPN-INV-DEP-00002).}';

    protected $description = 'Ata cada fila de matriz de depósito al espécimen que produjo en la colección';

    public function handle(IngresoColeccionPort $ingresoColeccion): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $numero = $this->option('solicitud');

        $solicitudes = SolicitudDepositoEloquentModel::query()
            ->when(is_string($numero) && $numero !== '', fn ($q) => $q->where('numero', $numero))
            ->orderBy('numero')
            ->get(['id', 'numero']);

        if ($solicitudes->isEmpty()) {
            $this->warn('No hay solicitudes de depósito que revisar.');

            return self::SUCCESS;
        }

        $this->line($aplicar ? 'Aplicando vínculos…' : 'Simulación (usa --aplicar para escribir).');
        $this->newLine();

        $filas = [];
        $conDiscrepancias = 0;

        foreach ($solicitudes as $solicitud) {
            $resultado = $ingresoColeccion->vincularRegistros((string) $solicitud->id, ! $aplicar);

            if (! $resultado->esConsistente()) {
                $conDiscrepancias++;
            }

            $filas[] = [
                (string) $solicitud->numero,
                $resultado->especimenesVinculados,
                $resultado->filasAnotadas,
                $resultado->yaVinculados,
                $resultado->sinEspecimen,
                $resultado->esConsistente() ? 'ok' : count($resultado->discrepancias).' discrepancias',
            ];

            foreach ($resultado->discrepancias as $discrepancia) {
                $this->warn(sprintf('  %s → %s', $solicitud->numero, $discrepancia));
            }
        }

        $this->newLine();
        $this->table(
            ['Solicitud', 'Vinculados', 'Filas anotadas', 'Ya atados', 'Sin espécimen', 'Estado'],
            $filas,
        );

        if ($conDiscrepancias > 0) {
            $this->error(
                sprintf(
                    '%d solicitud(es) con discrepancias: no se escribió nada en ellas. Revísalas antes de insistir.',
                    $conDiscrepancias,
                )
            );

            return self::FAILURE;
        }

        if (! $aplicar) {
            $this->info('Sin discrepancias. Vuelve a correrlo con --aplicar para escribir los vínculos.');
        }

        return self::SUCCESS;
    }
}
