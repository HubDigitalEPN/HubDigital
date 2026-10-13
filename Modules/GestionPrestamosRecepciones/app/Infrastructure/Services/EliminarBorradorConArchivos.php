<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;

final class EliminarBorradorConArchivos
{
    public function __invoke(SolicitudDepositoEloquentModel $borrador): void
    {
        DB::transaction(function () use ($borrador): void {
            $vigente = SolicitudDepositoEloquentModel::query()->whereKey($borrador->id)->lockForUpdate()->firstOrFail();
            abort_unless($vigente->estado === EstadoSolicitudDeposito::EnBorrador->value, 409,
                'La solicitud ya no es un borrador.');
            $cola = app(ColaFirmasSolicitudR2::class);
            $rutas = $cola->cancelarSolicitud($vigente->id);
            foreach (($vigente->documentos_cargados ?? []) as $ruta) {
                if (is_string($ruta) && $ruta !== '') $rutas[] = $ruta;
            }
            foreach (($vigente->documentos_adjuntos ?? []) as $documento) {
                if (is_array($documento) && is_string($documento['ruta'] ?? null)) $rutas[] = $documento['ruta'];
            }
            $rutas[] = $vigente->solicitud_firmada_ruta;
            $rutas[] = $vigente->acta_transferencia_dominio['ruta'] ?? null;
            foreach (array_unique(array_filter($rutas, fn ($ruta): bool => is_string($ruta) && $ruta !== '')) as $ruta) {
                app(AlmacenamientoDepositos::class)->eliminar($ruta);
            }
            app(GeneradorPdfSolicitudDeposito::class)->olvidar($vigente);
            $cola->retirarSolicitud($vigente->id);
            $vigente->delete();
        });
    }
}
