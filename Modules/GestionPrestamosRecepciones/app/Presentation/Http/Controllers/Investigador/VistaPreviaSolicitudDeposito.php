<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;

/** Vista previa en memoria: permite campos pendientes, sin registrar ni firmar un oficio. */
final class VistaPreviaSolicitudDeposito
{
    public function __invoke(Request $request, GeneradorPdfSolicitudDeposito $generador): Response
    {
        $reglas = [];
        foreach ([
            'solicitud_nombre_permiso' => 200, 'solicitud_tipo_identificacion' => 20,
            'solicitud_cedula' => 20, 'solicitud_cargo' => 200, 'solicitud_grupo' => 200,
            'solicitud_proyecto' => 300, 'solicitud_institucion' => 250,
            'solicitud_correo' => 250, 'solicitud_oficio' => 100,
        ] as $campo => $maximo) {
            $reglas[$campo] = ['nullable', 'string', 'max:'.$maximo];
        }
        $solicitud = new SolicitudDepositoEloquentModel($request->validate($reglas));
        $solicitud->investigador_id = (string) $request->user()->id;
        $solicitud->created_at = now();

        return response($generador->previsualizar($solicitud), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Vista-previa-solicitud.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
