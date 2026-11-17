<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\DocumentoIngresoDeposito;

final class DocumentoIngreso
{
    public function __invoke(Request $request, string $id, DocumentoIngresoDeposito $documentos): Response|\Illuminate\Contracts\View\View
    {
        $solicitud = Solicitud::findOrFail($id);
        abort_unless($request->user()?->esCurador() || (string) $request->user()?->id === (string) $solicitud->investigador_id, 403);
        if ($request->routeIs('depositos.ingreso.descargar')) {
            abort_unless($solicitud->estado === 'Aprobada Documentalmente' && filled($solicitud->codigo_qr), 409);
            return view('gestionprestamosrecepciones::investigador.documento-ingreso-listo', ['solicitud' => $solicitud]);
        }
        $pdf = $documentos->contenido($id);
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('ver') ? 'inline' : 'attachment').'; filename="Ingreso-'.$solicitud->numero.'.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
