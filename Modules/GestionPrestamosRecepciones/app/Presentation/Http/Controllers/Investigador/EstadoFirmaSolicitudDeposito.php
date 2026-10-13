<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;

final class EstadoFirmaSolicitudDeposito
{
    public function __invoke(Request $request, string $id, AlmacenamientoDepositos $almacenamiento): JsonResponse
    {
        $solicitud = SolicitudDepositoEloquentModel::query()->whereKey($id)
            ->where('investigador_id', (string) $request->user()->id)->firstOrFail();
        $ruta = $solicitud->solicitud_firmada_ruta;
        $firmada = $solicitud->solicitud_firmada_en !== null
            && (int) $request->query('version') === (int) $solicitud->solicitud_documento_version
            && is_string($ruta) && $ruta !== ''
            && is_string($solicitud->solicitud_firmada_sha256)
            && (app(ColaFirmasSolicitudR2::class)->disponible($solicitud)
                || ($almacenamiento->existe($ruta)
                    && hash_equals($solicitud->solicitud_firmada_sha256, $almacenamiento->sha256($ruta))));

        return response()->json(['firmada' => $firmada], 200, ['Cache-Control' => 'private, no-store']);
    }
}
