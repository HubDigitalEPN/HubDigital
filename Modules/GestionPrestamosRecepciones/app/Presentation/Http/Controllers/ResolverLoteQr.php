<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Http\RedirectResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverLotePorCodigoQr\ResolverLotePorCodigoQrHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverLotePorCodigoQr\ResolverLotePorCodigoQrInput;

/**
 * Punto de entrada del Código QR del lote. Resuelve la solicitud a partir del código
 * escaneado y redirige de forma inteligente según el actor autenticado: el curador va
 * a la recepción física, el depositante dueño al detalle de su depósito.
 */
final class ResolverLoteQr
{
    public function __invoke(string $codigo, ResolverLotePorCodigoQrHandler $handler): RedirectResponse
    {
        $lote = $handler->handle(new ResolverLotePorCodigoQrInput($codigo));

        abort_if($lote === null, 404);

        $user = auth()->user();

        if ($user?->rol === RolUsuario::CURADOR) {
            return redirect()->route('prestamos.curador.deposito.recepcion', $lote->solicitudId);
        }

        if ($user?->rol === RolUsuario::DEPOSITANTE && (string) $user->id === $lote->investigadorId) {
            return redirect()->route('prestamos.investigador.deposito.detalle', $lote->solicitudId);
        }

        abort(403);
    }
}
