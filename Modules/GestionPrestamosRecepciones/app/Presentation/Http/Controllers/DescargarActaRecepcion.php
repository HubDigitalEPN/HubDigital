<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;

/**
 * Sirve el Acta de Recepción de una recepción finalizada como PDF.
 *
 * Si el curador ya subió el PDF firmado electrónicamente (FirmaEC), entrega esa versión
 * firmada; en caso contrario genera al vuelo el PDF sin firmar (para que el curador lo
 * descargue, lo firme con FirmaEC y lo vuelva a subir). Accesible por el curador o por el
 * depositante dueño.
 */
final class DescargarActaRecepcion
{
    public function __invoke(
        string $id,
        ConsultarDetalleRecepcionHandler $handler,
        UsuarioNombrePort $usuarios,
    ): Response {
        $recepcion = $handler->handle(new ConsultarDetalleRecepcionInput($id));

        abort_if($recepcion === null, 404);
        // El acta solo existe cuando la recepción fue finalizada (verificada o con observaciones).
        abort_unless($recepcion->actaEmitida, 404);

        $user = auth()->user();
        $esCurador = $user?->rol === RolUsuario::CURADOR;
        $esDueno = $recepcion->investigadorId === (string) $user?->id;
        abort_unless($esCurador || $esDueno, 403);

        $filename = 'Acta-recepcion-'.$recepcion->numeroSolicitud.'.pdf';

        // Versión firmada electrónicamente ya disponible: se entrega tal cual.
        if ($recepcion->actaFirmada && $recepcion->actaFirmadaRuta !== null
            && Storage::disk('public')->exists($recepcion->actaFirmadaRuta)) {
            return response(Storage::disk('public')->get($recepcion->actaFirmadaRuta), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        // Aún sin firmar: se genera el PDF a partir de la plantilla del oficio.
        $depositante = $usuarios->obtenerDatosDepositante($recepcion->investigadorId);
        $curador = $recepcion->curadorResponsable !== null
            ? $usuarios->obtenerNombre($recepcion->curadorResponsable)
            : null;

        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
            'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $hoy = now();
        $fecha = sprintf('%d de %s de %d', $hoy->day, $meses[$hoy->month - 1], $hoy->year);

        $pdf = Pdf::loadView('gestionprestamosrecepciones::pdf.acta-recepcion', [
            'recepcion' => $recepcion,
            'depositante' => $depositante,
            'investigador' => $depositante?->nombre ?? $recepcion->investigadorId,
            'curador' => $curador,
            'fecha' => $fecha,
        ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
