<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Sirve, mediante streaming autenticado, el Acta de Transferencia de Dominio de una
 * donación aprobada.
 *
 * Acceso permitido solo al curador o al depositante dueño de la solicitud. Se entrega
 * `inline`; con `?descargar=1` se entrega como descarga.
 */
final class ServirActaTransferenciaDeposito
{
    public function __invoke(string $id): Response
    {
        $user = auth()->user();

        $deposito = SolicitudDepositoEloquentModel::find($id);
        abort_if($deposito === null, 404);

        $esCurador = $user?->rol === RolUsuario::CURADOR;
        $esDueno = (string) $deposito->investigador_id === (string) $user?->id;
        abort_unless($esCurador || $esDueno, 403);

        $ruta = $deposito->acta_transferencia_dominio['ruta'] ?? null;
        abort_if($ruta === null || ! Storage::disk('public')->exists($ruta), 404);

        $disposicion = request()->boolean('descargar') ? 'attachment' : 'inline';

        return response(Storage::disk('public')->get($ruta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicion.'; filename="acta-transferencia-'.$id.'.pdf"',
        ]);
    }
}
