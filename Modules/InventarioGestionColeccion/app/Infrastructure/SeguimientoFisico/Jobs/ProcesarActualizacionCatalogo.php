<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Jobs;

use App\Models\User;
use App\Support\AccesoCatalogo;
use DomainException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ActualizacionCatalogo;
use Throwable;

final class ProcesarActualizacionCatalogo implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    // Menor que retry_after=90 del consumidor existente: no se reserva dos veces.
    public int $timeout = 60;

    public function __construct(public readonly string $importacionId) {}

    public function handle(ActualizacionCatalogo $importador, AlmacenamientoDepositos $almacenamiento): void
    {
        $carga = DB::table('taxonomia.importaciones_catalogo')->where('id', $this->importacionId)->first();
        if (!$carga || $carga->estado === 'completada') return;
        $actor = User::find($carga->creado_por_id);
        if (!$actor || !AccesoCatalogo::completo($actor)) throw new DomainException('La cuenta que solicitó la carga ya no tiene acceso de curaduría.');
        DB::table('taxonomia.importaciones_catalogo')->where('id', $this->importacionId)->update(['estado' => 'procesando', 'updated_at' => now()]);
        $archivo = $almacenamiento->copiaLocal($carga->archivo_ruta);
        try { $importador->aplicar($this->importacionId, $archivo->ruta()); }
        finally { $archivo->limpiar(); }
    }

    public function failed(?Throwable $error): void
    {
        $mensaje = $error instanceof DomainException ? $error->getMessage() : 'No se pudo completar la carga. El catálogo se conserva sin cambios. Vuelve a validar y reintenta.';
        if ($error instanceof \Illuminate\Queue\TimeoutExceededException) $mensaje = 'La matriz tardó demasiado en procesarse. Divide el archivo en matrices más pequeñas y vuelve a cargarlo. No se guardaron cambios parciales.';
        DB::table('taxonomia.importaciones_catalogo')->where('id', $this->importacionId)->where('estado', '!=', 'completada')
            ->update(['estado' => 'fallida', 'error' => $mensaje, 'updated_at' => now()]);
    }
}
