<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Tests\DatabaseFeatureTestCase;

/** Simula únicamente el dictamen de admisión ya emitido por el servidor; no prueba seguridad PDF. */
function encolarDocumentoAdmitidoParaPruebas(SolicitudDepositoEloquentModel $modelo, string $contenido): string
{
    $nombre = 'Documento de procedencia de los especimenes';
    $temporal = DirectorioTemporalHubDigital::crearArchivo('prueba-cola-documento', 'pdf-');
    file_put_contents($temporal, $contenido);
    try {
        return DB::transaction(function () use ($modelo, $nombre, $contenido, $temporal): string {
            $ruta = 'depositos/'.$modelo->id.'/'.Str::uuid().'.pdf';
            $anteriores = $modelo->documentos_cargados ?? [];
            $previas = $modelo->validacion_previa_documentos ?? [];
            $token = (string) Str::uuid();
            $previas[$nombre] = ['ruta' => $ruta, 'sha256' => hash('sha256', $contenido),
                'estructura' => ['status' => 'seguro'], 'verificacion_id' => $token];
            $modelo->forceFill(['documentos_requeridos' => [$nombre],
                'documentos_cargados' => [...$anteriores, $nombre => $ruta], 'validacion_previa_documentos' => $previas])->save();
            $id = app(ColaFirmasSolicitudR2::class)->encolarDocumento($temporal, $modelo, $nombre, $anteriores[$nombre] ?? null);
            $previas[$nombre] += ['r2_pendiente_id' => $id, 'r2_verificacion_id' => $token];
            $modelo->forceFill(['validacion_previa_documentos' => $previas])->save();
            return $id;
        });
    } finally { DirectorioTemporalHubDigital::eliminar(dirname($temporal)); }
}
