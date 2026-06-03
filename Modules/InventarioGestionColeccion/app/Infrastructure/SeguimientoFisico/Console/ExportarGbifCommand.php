<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive\ExportarDarwinCoreArchiveHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive\ExportarDarwinCoreArchiveInput;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\DwcArchivePackager;

/**
 * Genera el Darwin Core Archive (DwC-A) y lo escribe a
 * `storage/app/exports/gbif/{timestamp}/dwc-a.zip` + los 4 archivos
 * sueltos en la misma carpeta. Útil para QA local antes de subir al IPT.
 *
 *   php artisan inventario:exportar-gbif
 *     [--incluir-no-publicables]    no recomendado en producción
 */
final class ExportarGbifCommand extends Command
{
    protected $signature = 'inventario:exportar-gbif
        {--incluir-no-publicables : Incluir especímenes que no cumplen CriteriosCalidadGbif}';

    protected $description = 'Genera el Darwin Core Archive y lo guarda en storage/app/exports/gbif/.';

    public function handle(ExportarDarwinCoreArchiveHandler $handler): int
    {
        $incluir = (bool) $this->option('incluir-no-publicables');

        $this->info('Generando Darwin Core Archive...');
        $this->newLine();

        try {
            $output = $handler->handle(new ExportarDarwinCoreArchiveInput(
                incluirNoPublicables: $incluir,
            ));
        } catch (\Throwable $e) {
            $this->error('Falló la exportación: '.$e->getMessage());

            return self::FAILURE;
        }

        $timestamp = date('Ymd-His');
        $carpeta = "exports/gbif/{$timestamp}";

        Storage::disk('local')->put("{$carpeta}/meta.xml", $output->metaXml);
        Storage::disk('local')->put("{$carpeta}/eml.xml", $output->emlXml);
        Storage::disk('local')->put("{$carpeta}/occurrence.txt", $output->occurrenceTxt);
        Storage::disk('local')->put("{$carpeta}/taxon.txt", $output->taxonTxt);

        $zipPath = storage_path("app/{$carpeta}/dwc-a.zip");
        DwcArchivePackager::empaquetar($output, $zipPath);

        $this->info($output->resumenLinea());
        $this->newLine();
        $this->line('  Archivos generados:');
        $this->line("    {$carpeta}/dwc-a.zip");
        $this->line("    {$carpeta}/meta.xml");
        $this->line("    {$carpeta}/eml.xml");
        $this->line("    {$carpeta}/occurrence.txt");
        $this->line("    {$carpeta}/taxon.txt");

        if ($output->motivosExclusion !== []) {
            $this->newLine();
            $this->line('<comment>Motivos de exclusión:</comment>');
            foreach ($output->motivosExclusion as $motivo => $conteo) {
                $this->line("  {$conteo}× {$motivo}");
            }
        }

        return self::SUCCESS;
    }
}
