<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive\ExportarDarwinCoreArchiveHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive\ExportarDarwinCoreArchiveInput;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\DwcArchivePackager;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga directa del Darwin Core Archive (ZIP con los 4 archivos).
 *
 * Acceso controlado por middleware role:curador (definido en routes/web.php).
 */
final class ExportarDwcController
{
    public function __invoke(Request $request, ExportarDarwinCoreArchiveHandler $handler): StreamedResponse|Response
    {
        $incluir = $request->boolean('incluir_no_publicables', false);

        try {
            $output = $handler->handle(new ExportarDarwinCoreArchiveInput(
                incluirNoPublicables: $incluir,
            ));
        } catch (\DomainException $e) {
            // DatasetConfig no configurado u otra precondición del dominio.
            return response($e->getMessage(), Response::HTTP_PRECONDITION_FAILED)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $zip = DwcArchivePackager::comoString($output);
        $timestamp = date('Ymd-His');
        $nombre = "mepn-inv-dwc-a_{$timestamp}.zip";

        return response()->streamDownload(
            function () use ($zip): void {
                echo $zip;
            },
            $nombre,
            [
                'Content-Type' => 'application/zip',
                'Content-Length' => (string) strlen($zip),
                'X-Dwca-Publicados' => (string) $output->publicados,
                'X-Dwca-Excluidos' => (string) $output->excluidos,
                'X-Dwca-Total' => (string) $output->totalEspecimenes,
            ]
        );
    }
}
