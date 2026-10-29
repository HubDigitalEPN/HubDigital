<?php

namespace App\Console\Commands;

use App\Services\IA\PresupuestoIA;
use Illuminate\Console\Command;

final class EstadoPresupuestoIACommand extends Command
{
    protected $signature = 'ia:presupuesto';
    protected $description = 'Muestra el presupuesto del agente sin consumir créditos ni modificar el saldo';

    public function handle(): int
    {
        $e = (new PresupuestoIA((string) config('ia.ledger')))->estado();
        if ($e === null) {
            $this->info('Qwen: OpenAI bloqueado por falta de un presupuesto privado válido.');
            return self::SUCCESS;
        }
        $restante = max(0, $e['limite'] - $e['gastado'] - array_sum($e['reservas']));
        $estado = $e['bloqueo'] ?? ($e['vence'] <= time() ? 'credito_vencido' : ($restante === 0 ? 'limite_alcanzado' : 'vigente'));
        $this->line(json_encode(['estado' => $estado, 'limite_usd' => $e['limite'] / 1_000_000,
            'disponible_usd' => $restante / 1_000_000, 'reservado_usd' => array_sum($e['reservas']) / 1_000_000,
            'vence_utc' => gmdate('c', $e['vence'])], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
