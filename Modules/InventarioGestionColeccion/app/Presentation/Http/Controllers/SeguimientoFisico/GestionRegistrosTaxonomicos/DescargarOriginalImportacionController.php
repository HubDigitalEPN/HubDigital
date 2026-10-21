<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use App\Support\AccesoCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class DescargarOriginalImportacionController
{
    public function __invoke(string $importacion)
    {
        AccesoCatalogo::autorizar();
        $carga = DB::table('taxonomia.importaciones_catalogo')->where('id', $importacion)->first();
        abort_unless($carga, 404);
        try { $contenido = app(AlmacenamientoDepositos::class)->obtenerVerificado($carga->archivo_ruta, $carga->fuente_sha256); }
        catch (\Throwable $e) { report($e); abort(409, 'No se pudo recuperar íntegro el archivo original. Comunícalo al administrador.'); }
        $mime = match ($carga->extension) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            default => 'text/csv; charset=UTF-8',
        };
        $nombre = basename($carga->archivo_nombre);
        $respaldo = preg_replace('/[^a-zA-Z0-9._ ()-]/', '_', Str::ascii($nombre));
        return response($contenido, 200, ['Content-Type' => $mime, 'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $nombre, $respaldo ?: 'matriz-original.'.$carga->extension), 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
