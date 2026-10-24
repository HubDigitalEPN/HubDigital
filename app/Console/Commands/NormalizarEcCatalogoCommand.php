<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NormalizadorEcCatalogo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class NormalizarEcCatalogoCommand extends Command
{
    protected $signature = 'catalogo:normalizar-ec {--aplicar : Aplicar la corrección explícita de EC en la base local} {--conflictos : Preparar un informe de las identidades que quedarían duplicadas} {--omitir-conflictos : Conservar sin cambios las identidades que quedarían duplicadas}';
    protected $description = 'Corrige el patrón EC en los textos curatoriales con respaldo de cada valor original';

    public function handle(NormalizadorEcCatalogo $servicio): int
    {
        if ($this->option('conflictos')) {
            $conexion = config('database.connections.'.config('database.default'));
            abort_unless(app()->environment('local') && in_array($conexion['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true), 403);
            $conflictos = $servicio->conflictos();
            $ruta = base_path('.local/respaldos-catalogo/conflictos-ec.json');
            File::ensureDirectoryExists(dirname($ruta));
            File::put($ruta, json_encode($conflictos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->line(json_encode(['conflictos' => count($conflictos), 'informe' => $ruta], JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }
        if (!$this->option('aplicar')) { $this->error('Indica --aplicar para realizar la corrección solicitada.'); return self::FAILURE; }
        $directorio = base_path('.local/respaldos-catalogo'); File::ensureDirectoryExists($directorio);
        $ruta = $directorio.'/normalizacion-ec-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.jsonl';
        try {
            $resumen = $servicio->ejecutar($ruta, (bool) $this->option('omitir-conflictos'));
            $this->line(json_encode($resumen, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $this->info('Respaldo local: '.$ruta); return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e); $this->error('No se aplicó la corrección. Revisa el registro local y el respaldo: '.$ruta); return self::FAILURE;
        }
    }
}
