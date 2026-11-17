<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverLotePorCodigoQr\ResolverLotePorCodigoQrHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverLotePorCodigoQr\ResolverLotePorCodigoQrInput;

/**
 * Punto de entrada del Código QR del lote. Resuelve la solicitud a partir del código
 * escaneado y redirige de forma inteligente según el actor autenticado: recepción EPN
 * constata el lote, curaduría revisa el expediente y el depositante dueño consulta su
 * trámite.
 */
final class ResolverLoteQr
{
    public function __invoke(string $codigo, ResolverLotePorCodigoQrHandler $handler): RedirectResponse
    {
        $lote = $handler->handle(new ResolverLotePorCodigoQrInput($codigo));

        abort_if($lote === null, 404);

        $user = auth()->user();

        if ($user?->esReceptor()) {
            return redirect()->to(url(app(\Modules\GestionPrestamosRecepciones\Infrastructure\Services\DocumentoIngresoDeposito::class)->urlQr(\Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel::findOrFail($lote->solicitudId))));
        }

        if ($user?->esCurador()) {
            return redirect()->route('prestamos.curador.deposito.revisar', $lote->solicitudId);
        }

        if ($user?->esDepositante() && (string) $user->id === $lote->investigadorId) {
            return redirect()->route('prestamos.investigador.deposito.detalle', $lote->solicitudId);
        }

        abort(403);
    }
}
