<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
        $solicitud = DB::transaction(function () use ($id): Solicitud {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($id);
            abort_unless($solicitud->estado === 'Aprobada Documentalmente' && filled($solicitud->codigo_qr), 409, 'La solicitud aún no tiene aprobación documental vigente.');
            if (! $solicitud->documento_ingreso_ruta) {
                app(IngresoPendienteDeposito::class)->registrar($id);
            }

            return $solicitud;
        });
        if ($solicitud->documento_ingreso_ruta) {
            return $this->archivos->obtenerVerificado($solicitud->documento_ingreso_ruta, $solicitud->documento_ingreso_sha256);
        }
        $snapshot = json_decode(DB::table(IngresoPendienteDeposito::TABLA)->where('solicitud_id', $id)->value('snapshot'), true, flags: JSON_THROW_ON_ERROR);
        $aprobada = new Solicitud;
        $aprobada->forceFill($snapshot);
        $url = url($this->urlQr($aprobada));
        // Render y almacenamiento fuera del bloqueo: una caída de R2 no retiene la transacción.
        $pdf = Pdf::loadView('gestionprestamosrecepciones::pdf.documento-ingreso', [
            'solicitud' => $aprobada, 'qr' => base64_encode(GeneradorQrSvg::svg($url, 220)), 'url' => $url,
        ])->setPaper('a4')->output();
        // Un candidato nunca sobrescribe los bytes de otro proceso concurrente.
        $ruta = 'documentos-ingreso/'.$id.'/'.Str::uuid().'.pdf';
        $sha = $this->archivos->guardarContenido($ruta, $pdf, 'application/pdf');
        $vigente = DB::transaction(function () use ($id, $ruta, $sha, $aprobada): Solicitud {
            $s = Solicitud::lockForUpdate()->findOrFail($id);
            abort_unless($s->estado === 'Aprobada Documentalmente' && $s->codigo_qr === $aprobada->codigo_qr, 409);
            if (! $s->documento_ingreso_ruta) {
                $s->forceFill(['documento_ingreso_ruta' => $ruta, 'documento_ingreso_sha256' => $sha])->save();
            }

            return $s;
        });

        return $vigente->documento_ingreso_ruta === $ruta ? $pdf
            : $this->archivos->obtenerVerificado($vigente->documento_ingreso_ruta, $vigente->documento_ingreso_sha256);
    }
}
