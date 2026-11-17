<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Un único PDF privado por solicitud, con QR autenticado y huella persistida. */
final class DocumentoIngresoDeposito
{
    public function __construct(private AlmacenamientoDepositos $archivos) {}

    public function urlQr(Solicitud $solicitud): string
    {
        return URL::signedRoute('depositos.ingreso.validar', ['id' => $solicitud->id, 'codigo' => $solicitud->codigo_qr], absolute: false);
    }

    public function contenido(string $id): string
    {
        return DB::transaction(function () use ($id): string {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($id);
            abort_unless($solicitud->estado === 'Aprobada Documentalmente' && filled($solicitud->codigo_qr), 409, 'La solicitud aún no tiene aprobación documental vigente.');
            if ($solicitud->documento_ingreso_ruta) {
                return $this->archivos->obtenerVerificado($solicitud->documento_ingreso_ruta, $solicitud->documento_ingreso_sha256);
            }
            $url = url($this->urlQr($solicitud));
            $pdf = Pdf::loadView('gestionprestamosrecepciones::pdf.documento-ingreso', [
                'solicitud' => $solicitud,
                'qr' => base64_encode(GeneradorQrSvg::svg($url, 220)),
                'url' => $url,
            ])->setPaper('a4')->output();
            $ruta = 'documentos-ingreso/'.$solicitud->id.'.pdf';
            $sha = $this->archivos->guardarContenido($ruta, $pdf, 'application/pdf');
            $solicitud->forceFill(['documento_ingreso_ruta' => $ruta, 'documento_ingreso_sha256' => $sha])->save();
            return $pdf;
        });
    }
}
