<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Comprueba la firma de un PDF al terminar su carga, sin sacar al usuario del paso 3. */
final class VerificarFirmaDocumentoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        private readonly string $solicitudId,
        private readonly string $nombre,
        private readonly string $ruta,
        private readonly string $verificacionId = '',
    ) {
        if (config('hubdigital.validation_mode')) {
            $this->onQueue((string) config('hubdigital.validation_queue'));
        }
    }

    public function handle(ValidacionFirmaElectronicaPort $validador, AlmacenamientoDepositos $almacenamiento): void
    {
        $actual = SolicitudDepositoEloquentModel::query()->find($this->solicitudId);
        if ($actual === null || ! $this->corresponde($actual)) {
            return;
        }

        $copia = $almacenamiento->copiaLocal($this->ruta);
        try {
            $esperada = $actual->validacion_previa_documentos[$this->nombre]['sha256'] ?? '';
            if (! is_string($esperada) || $esperada === ''
                || ! hash_equals($esperada, (string) hash_file('sha256', $copia->ruta()))) {
                throw new \RuntimeException('El objeto PDF no coincide con el contenido previamente inspeccionado.');
            }
            $estado = $validador->verificarFirma($copia->ruta())->value;
        } finally {
            $copia->limpiar();
        }

        $this->guardarEstado($estado);
    }

    public function failed(\Throwable $error): void
    {
        Log::error('Falló la comprobación automática de firma PDF', [
            'solicitud_id' => $this->solicitudId,
            'documento' => $this->nombre,
            'error' => $error->getMessage(),
        ]);
        $this->guardarEstado('verificacion_no_disponible');
    }

    private function guardarEstado(string $estado): void
    {
        DB::transaction(function () use ($estado): void {
            $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->lockForUpdate()->first();
            if ($modelo === null || ! $this->corresponde($modelo)) {
                return;
            }

            $firmas = $modelo->firmas_electronicas ?? [];
            $firmas[$this->nombre] = $estado;
            $previas = $modelo->validacion_previa_documentos ?? [];
            $previas[$this->nombre]['firma_verificada'] = $estado !== 'verificacion_no_disponible';
            $previas[$this->nombre]['firma_verificada_en'] = now()->toIso8601String();
            $modelo->forceFill(['firmas_electronicas' => $firmas, 'validacion_previa_documentos' => $previas])->save();

            DB::table('recepciones.documentos_regulatorios')
                ->where('solicitud_id', $this->solicitudId)
                ->where('ruta', $this->ruta)
                ->update(['firma_estado' => $estado, 'firma_verificada_en' => now(), 'updated_at' => now()]);
        });
    }

    private function corresponde(SolicitudDepositoEloquentModel $modelo): bool
    {
        $previa = $modelo->validacion_previa_documentos[$this->nombre] ?? [];

        return ($modelo->documentos_cargados[$this->nombre] ?? null) === $this->ruta
            && ($modelo->validacion_archivos[$this->nombre] ?? null) === 'valido'
            && ($modelo->firmas_electronicas[$this->nombre] ?? null) === 'validando'
            && ($previa['ruta'] ?? null) === $this->ruta
            && ($this->verificacionId ?? '') !== ''
            && hash_equals((string) ($previa['verificacion_id'] ?? ''), $this->verificacionId);
    }
}
