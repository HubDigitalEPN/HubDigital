<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\ProcesarIngresoDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\IngresoPendienteDeposito;

final class ProcesarIngresosPendientesCommand extends Command
{
    protected $signature = 'depositos:ingresos-pendientes';

    protected $description = 'Despacha hasta 50 ingresos pendientes, con recuperación y reintentos acotados.';

    public function handle(): int
    {
        DB::table(IngresoPendienteDeposito::TABLA)->whereIn('estado', ['pendiente', 'procesando'])
            ->where(fn ($q) => $q->whereNull('ocupado_hasta')->orWhere('ocupado_hasta', '<=', now()->toIso8601String()))
            ->where(fn ($q) => $q->whereNull('reintentar_en')->orWhere('reintentar_en', '<=', now()->toIso8601String()))
            ->where(fn ($q) => $q->whereNull('despachado_en')->orWhere('despachado_en', '<=', now()->subMinutes(6)->toIso8601String()))
            ->orderBy('created_at')->limit(50)->get()->each(function ($r): void {
                // El plazo impide llenar la cola si el worker está detenido. Una caída se recupera a los 6 minutos.
                DB::table(IngresoPendienteDeposito::TABLA)->where('id', $r->id)->update(['despachado_en' => now()->toIso8601String()]);
                Bus::dispatch(new ProcesarIngresoDeposito($r->solicitud_id));
            });

        return self::SUCCESS;
    }
}
