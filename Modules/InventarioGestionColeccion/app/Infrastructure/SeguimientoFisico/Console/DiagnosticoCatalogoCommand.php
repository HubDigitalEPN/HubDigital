<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console;

use Illuminate\Console\Command;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EspecimenEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\TaxonEloquentModel;

/**
 * Diagnóstico de SOLO LECTURA del catálogo: ayuda a entender por qué una
 * búsqueda de especímenes puede no devolver nada. No modifica ningún dato.
 *
 *   php artisan inventario:diagnostico-catalogo
 *   php artisan inventario:diagnostico-catalogo --taxon=Animalia
 */
final class DiagnosticoCatalogoCommand extends Command
{
    protected $signature = 'inventario:diagnostico-catalogo {--taxon= : Nombre de taxón a probar (ej. Animalia)}';

    protected $description = 'Reporta el estado del catálogo para diagnosticar búsquedas vacías (solo lectura).';

    public function handle(): int
    {
        $totalEsp = EspecimenEloquentModel::count();
        $conTaxon = EspecimenEloquentModel::whereNotNull('taxon_id')->count();
        $conVerbatim = EspecimenEloquentModel::whereNotNull('taxon_verbatim')->where('taxon_verbatim', '!=', '')->count();
        $pendientes = EspecimenEloquentModel::where('estado_revision', 'pendiente')->whereNotNull('motivo_revision')->count();
        $totalTax = TaxonEloquentModel::count();

        $this->info("Especímenes totales: {$totalEsp}");
        $this->line('  • enlazados a un taxón (taxon_id): '.$conTaxon);
        $this->line('  • SIN taxón enlazado: '.($totalEsp - $conTaxon));
        $this->line('  • con taxon_verbatim: '.$conVerbatim);
        $this->line('  • marcados para revisión (pendiente + motivo): '.$pendientes);
        $this->info("Taxones totales: {$totalTax}");

        $this->newLine();
        $this->line('<comment>Ejemplos de especímenes:</comment>');
        $ejemplos = EspecimenEloquentModel::query()
            ->limit(5)
            ->get(['codigo_catalogo', 'taxon_id', 'taxon_verbatim', 'colector', 'estado_revision']);
        foreach ($ejemplos as $e) {
            $this->line(sprintf(
                '  cod=%s | taxon_id=%s | verbatim=%s | colector=%s | rev=%s',
                $e->codigo_catalogo ?? '—',
                $e->taxon_id ?? 'NULL',
                $e->taxon_verbatim ?? 'NULL',
                $e->colector ?? '—',
                $e->estado_revision ?? '—',
            ));
        }

        $taxon = $this->option('taxon');
        if ($taxon !== null && $taxon !== '') {
            $this->newLine();
            $this->line("<comment>Prueba de búsqueda por taxón: '{$taxon}'</comment>");

            $taxaCoincidentes = TaxonEloquentModel::where('nombre_cientifico', 'ilike', "%{$taxon}%")->get(['id', 'nombre_cientifico', 'rango']);
            $this->line('  taxones que coinciden por nombre: '.$taxaCoincidentes->count());
            foreach ($taxaCoincidentes as $t) {
                $this->line("    - {$t->nombre_cientifico} ({$t->rango})");
            }

            $porVerbatim = EspecimenEloquentModel::where('taxon_verbatim', 'ilike', "%{$taxon}%")->count();
            $this->line("  especímenes con taxon_verbatim que contiene '{$taxon}': {$porVerbatim}");

            if ($taxaCoincidentes->isNotEmpty()) {
                $idsCoincidentes = $taxaCoincidentes->pluck('id')->all();
                $enlazadosDirecto = EspecimenEloquentModel::whereIn('taxon_id', $idsCoincidentes)->count();
                $this->line("  especímenes enlazados DIRECTAMENTE a esos taxones: {$enlazadosDirecto}");
            }
        }

        $this->newLine();
        $this->line('<info>Cómo leerlo:</info>');
        $this->line('  - Si "enlazados a un taxón" es 0, los especímenes no tienen taxon_id: la búsqueda por taxón solo los halla por taxon_verbatim.');
        $this->line('  - Si hay taxones que coinciden pero "enlazados DIRECTAMENTE" es 0, la jerarquía existe pero los especímenes cuelgan de taxones que no matchean el nombre (usa un rango más específico o revisa el enlace).');

        return self::SUCCESS;
    }
}
