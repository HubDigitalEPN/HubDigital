<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Un presupuesto por petición; SET LOCAL no altera otras conexiones ni peticiones. */
final class PresupuestoConsultaPortal
{
    private int $inicio;

    public function __construct(private readonly string $seleccion)
    {
        $this->inicio = hrtime(true);
    }

    public function ejecutar(callable $calcular): array
    {
        try {
            return DB::transaction(fn (): array => $this->etapa('agregados', $calcular));
        } catch (QueryException|DeadlockException $error) {
            throw new ConsultaMapaNoDisponible('La consulta del mapa no está disponible.', previous: $error);
        }
    }

    public function etapa(string $nombre, callable $calcular): mixed
    {
        $restante = 20000 - (int) ((hrtime(true) - $this->inicio) / 1000000);
        if ($restante <= 0) throw new ConsultaMapaNoDisponible('La consulta del mapa superó su tiempo disponible.');
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::selectOne("SELECT set_config('statement_timeout', ?, true), set_config('lock_timeout', ?, true)", [
                min(8000, $restante).'ms', min(2000, $restante).'ms',
            ]);
        }
        $inicio = hrtime(true);
        try {
            return $calcular();
        } finally {
            Log::info('portal.mapa.etapa', [
                'etapa' => $nombre, 'duracion_ms' => round((hrtime(true) - $inicio) / 1000000, 1),
                'total_ms' => round((hrtime(true) - $this->inicio) / 1000000, 1),
                'seleccion' => $this->seleccion, 'ray' => mb_substr((string) request()->header('CF-Ray'), 0, 100),
                'memoria_bytes' => memory_get_peak_usage(true),
            ]);
        }
    }
}
