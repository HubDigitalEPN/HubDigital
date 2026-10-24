<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

Artisan::command('hub:prepare-local-views', function (): int {
    if (! app()->environment('local')) {
        $this->error('Esta preparación está reservada a localhost.');
        return 1;
    }

    $compiler = app('blade.compiler');
    $finder = app('view')->getFinder();
    // Conservar exactamente las rutas del finder: en Windows, normalizarlas
    // cambia la huella de Blade y deja sin preparar las vistas de la web.
    $paths = array_unique([
        ...$finder->getPaths(),
        ...array_merge([], ...array_values($finder->getHints())),
        ...array_column($compiler->getAnonymousComponentPaths(), 'path'),
    ]);
    $compiled = 0;
    foreach ($paths as $path) {
        $realPath = realpath($path);
        if (! $realPath || ! str_starts_with($realPath, base_path().DIRECTORY_SEPARATOR)
            || str_starts_with($realPath, base_path('vendor').DIRECTORY_SEPARATOR)) continue;
        foreach (File::allFiles($path) as $file) {
            $name = $path.'/'.str_replace('\\', '/', $file->getRelativePathname());
            if (str_ends_with($name, '.blade.php') && $compiler->isExpired($name)) {
                $compiler->compile($name);
                $compiled++;
            }
        }
    }

    $this->info("Vistas locales preparadas: {$compiled}.");
    return 0;
})->purpose('Prepara las vistas de la aplicación sin recompilar las vistas vigentes ni los iconos de proveedores');

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
