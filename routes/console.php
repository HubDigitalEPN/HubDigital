<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('portal-chat:prune-unmatched {--days=180} {--apply}', function (): int {
    $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
    if ($days === false || $days < 30) {
        $this->error('La retención mínima es de 30 días.');
        return 1;
    }
    $query = DB::table('divulgacion.chat_unmatched')
        ->whereIn('status', ['resolved', 'ignored'])
        ->where('updated_at', '<', now()->subDays($days));
    $count = (clone $query)->count();
    if ($this->option('apply')) {
        $query->delete();
        $this->info("Se eliminaron {$count} muestras cerradas con más de {$days} días.");
    } else {
        $this->info("Simulación: {$count} muestras cerradas con más de {$days} días. Usa --apply para eliminarlas.");
    }
    return 0;
})->purpose('Simula o aplica retención de muestras cerradas del asistente; conserva pendientes y métricas');
