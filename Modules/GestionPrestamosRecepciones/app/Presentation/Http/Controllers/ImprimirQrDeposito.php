<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Contracts\View\View;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\GeneradorQrSvg;

/**
 * Muestra una página imprimible con el Código QR del lote de una solicitud aprobada,
 * para adjuntarlo a la entrega física de las muestras. La página abre el diálogo de
 * impresión del navegador, desde donde se puede guardar como PDF.
 *
 * Acceso permitido solo al curador o al depositante dueño de la solicitud.
 */
final class ImprimirQrDeposito
{
    public function __invoke(string $id, UsuarioNombrePort $usuarios): View
    {
        $user = auth()->user();

        $deposito = SolicitudDepositoEloquentModel::find($id);
        abort_if($deposito === null, 404);

        $esCurador = $user?->esCurador() ?? false;
        $esDueno = (string) $deposito->investigador_id === (string) $user?->id;
        abort_unless($esCurador || $esDueno, 403);

        abort_if(($deposito->codigo_qr ?? '') === '', 404);

        return view('gestionprestamosrecepciones::curador.qr-imprimible', [
            'codigo' => $deposito->codigo_qr,
            'qrBase64' => base64_encode(GeneradorQrSvg::svg(route('prestamos.lote.resolver', $deposito->codigo_qr), 360)),
            'numero' => $deposito->numero,
            'tipoTramite' => $deposito->tipo_tramite,
            'investigador' => $usuarios->obtenerNombre($deposito->investigador_id)
                ?? $deposito->nombre_investigador_documento
                ?? $deposito->investigador_id,
            'fechaAprobacion' => $deposito->aprobada_en?->format('d/m/Y'),
        ]);
    }
}
