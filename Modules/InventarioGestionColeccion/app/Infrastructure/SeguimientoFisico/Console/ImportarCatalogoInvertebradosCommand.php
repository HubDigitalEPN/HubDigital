<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console;

use Illuminate\Console\Command;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\CsvFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FuenteCatalogoGeocodificada;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ImportarCatalogoInvertebrados;

/**
 * Comando artisan para importar el catálogo de invertebrados desde CSV.
 *
 *   php artisan inventario:importar-catalogo /path/to/catalogo.csv
 *     [--dry-run]            no persiste, sólo cuenta y reporta motivos
 *     [--from=N --to=N]      rango de filas (1-indexed, sin contar headers)
 *     [--chunk=500]          tamaño de batch (cosmético por ahora)
 *     [--delimiter=,]        separador CSV
 *
 * Pre-requisito: el Excel original debe exportarse a CSV (UTF-8) antes.
 * Razón: evitamos una dependencia de phpoffice/phpspreadsheet y procesamos
 * en streaming.
 */
final class ImportarCatalogoInvertebradosCommand extends Command
{
    protected $signature = 'inventario:importar-catalogo
        {ruta : Ruta absoluta al archivo CSV}
        {--dry-run : No persistir; sólo contar y reportar}
        {--from=1 : Fila inicial (1-indexed, sin headers)}
        {--to= : Fila final (inclusivo). Si se omite, lee hasta el final}
        {--chunk=500 : Tamaño de batch}
        {--delimiter=, : Separador CSV}
        {--geocodificar : Completar país/provincia/cantón/localidad faltantes por coordenadas (Nominatim, ~1/seg)}';

    protected $description = 'Importa el catálogo de invertebrados desde CSV. Cero pérdida: toda fila se preserva con verbatims y FK nullables.';

    public function handle(ImportarCatalogoInvertebrados $importer, GeocodificadorInversoPort $geo): int
    {
        $ruta = (string) $this->argument('ruta');
        if (! is_file($ruta)) {
            $this->error("Archivo no encontrado: '{$ruta}'.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $desde = (int) $this->option('from');
        $hastaOpt = $this->option('to');
        $hasta = $hastaOpt !== null && $hastaOpt !== '' ? (int) $hastaOpt : null;
        $chunk = (int) $this->option('chunk');
        $delimiter = (string) $this->option('delimiter');
        $geocodificar = (bool) $this->option('geocodificar');

        $this->info("Importando catálogo desde: {$ruta}");
        $this->line('  • modo: '.($dryRun ? 'DRY-RUN' : 'PERSISTIDO'));
        $this->line("  • rango: filas {$desde} → ".($hasta ?? 'final'));
        $this->line("  • chunk: {$chunk}");
        $this->line("  • delimitador: '{$delimiter}'");
        $this->line('  • geocodificar: '.($geocodificar ? 'SÍ (Nominatim, ~1/seg)' : 'no'));
        $this->newLine();

        $fuente = new CsvFuenteCatalogo($ruta, $delimiter);

        $fuenteGeo = null;
        if ($geocodificar) {
            // Sin tope en CLI: aquí no hay timeout de petición web.
            $fuente = $fuenteGeo = new FuenteCatalogoGeocodificada(
                $fuente,
                $geo,
                new FilaCatalogoMapper,
                throttleMs: 1100,
                maxLookups: null,
            );
        }

        $resultado = $importer->ejecutar(
            fuente: $fuente,
            dryRun: $dryRun,
            desde: $desde,
            hasta: $hasta,
            chunk: $chunk,
        );

        $this->newLine();
        $this->info($resultado->resumenLinea());

        if ($fuenteGeo !== null) {
            $this->line(
                "  • geocodificación: {$fuenteGeo->lookupsRealizados()} coordenadas consultadas, ".
                "{$fuenteGeo->filasEnriquecidas()} filas completadas"
            );
        }

        if ($resultado->motivosRevision !== []) {
            $this->newLine();
            $this->line('<comment>Top motivos de revisión:</comment>');
            foreach ($resultado->motivosRevision as $motivo => $conteo) {
                $this->line("  {$conteo}× {$motivo}");
            }
        }

        if ($resultado->erroresFatales !== []) {
            $this->newLine();
            $this->error('Errores fatales (filas no persistidas):');
            foreach (array_slice($resultado->erroresFatales, 0, 10) as $err) {
                $this->line("  fila {$err['fila']}: {$err['error']}");
            }
            $totales = count($resultado->erroresFatales);
            if ($totales > 10) {
                $this->line('  ... y '.($totales - 10).' errores más');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
