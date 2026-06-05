<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive\ExportarDarwinCoreArchiveOutput;
use ZipArchive;

/**
 * Empaquetador del Darwin Core Archive: combina los 4 archivos del handler
 * (meta.xml + eml.xml + occurrence.txt + taxon.txt) en un único ZIP que
 * GBIF/IPT pueden ingerir.
 *
 * Mantiene el handler libre de side-effects de filesystem. Los callers
 * (comando artisan, controlador HTTP) deciden qué hacer con el ZIP final.
 */
final class DwcArchivePackager
{
    /**
     * Escribe los 4 archivos como un .zip en `$rutaDestino`.
     * Asegura que la carpeta padre exista.
     *
     * @throws \RuntimeException si ZipArchive no logra crear el archivo.
     */
    public static function empaquetar(ExportarDarwinCoreArchiveOutput $output, string $rutaDestino): void
    {
        $directorio = dirname($rutaDestino);
        if (! is_dir($directorio) && ! mkdir($directorio, 0777, true) && ! is_dir($directorio)) {
            throw new \RuntimeException("No se pudo crear el directorio '{$directorio}'.");
        }

        $zip = new ZipArchive;
        if ($zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("No se pudo abrir el ZIP '{$rutaDestino}' para escritura.");
        }
        $zip->addFromString('meta.xml', $output->metaXml);
        $zip->addFromString('eml.xml', $output->emlXml);
        $zip->addFromString('occurrence.txt', $output->occurrenceTxt);
        $zip->addFromString('taxon.txt', $output->taxonTxt);
        $zip->close();
    }

    /**
     * Devuelve el contenido del ZIP como string en memoria. Útil para
     * streamDownload sin pasar por filesystem.
     */
    public static function comoString(ExportarDarwinCoreArchiveOutput $output): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dwca_');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal para el ZIP.');
        }
        try {
            self::empaquetar($output, $tmp);
            $contenido = file_get_contents($tmp);
            if ($contenido === false) {
                throw new \RuntimeException("No se pudo leer el ZIP temporal '{$tmp}'.");
            }

            return $contenido;
        } finally {
            @unlink($tmp);
        }
    }
}
