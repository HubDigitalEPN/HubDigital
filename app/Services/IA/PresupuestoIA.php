<?php

declare(strict_types=1);

namespace App\Services\IA;

/** Saldo durable en microdólares. El caché y el cambio de mes nunca lo reinician. */
final class PresupuestoIA
{
    public function __construct(private readonly string $ruta) {}

    public function inicializar(string $organizacion, string $proyecto, int $saldoMicros, int $vence): void
    {
        if ($organizacion === '' || $proyecto === '' || $saldoMicros <= 0 || $vence <= time()) {
            throw new \InvalidArgumentException('Crédito gratuito no vigente.');
        }
        if (! is_dir(dirname($this->ruta)) && ! mkdir(dirname($this->ruta), 0700, true) && ! is_dir(dirname($this->ruta))) {
            throw new \RuntimeException('No se puede preparar el presupuesto privado.');
        }
        $this->transaccion(function (?array $estado) use ($organizacion, $proyecto, $saldoMicros, $vence): array {
            // Repetir la instalación o desplegar otra release no repone dinero gastado.
            if ($estado !== null) throw new \RuntimeException('Ya existe un presupuesto; no se reinicia.');
            return ['version' => 1, 'organizacion' => $organizacion, 'proyecto' => $proyecto,
                'vence' => $vence, 'limite' => intdiv($saldoMicros * 95, 100),
                'gastado' => 0, 'reservas' => [], 'bloqueo' => null];
        }, true);
    }

    public function reservar(string $organizacion, string $proyecto, int $maximo): ?string
    {
        if ($maximo <= 0) return null;
        $id = bin2hex(random_bytes(16));
        $aceptada = false;
        try {
            $this->transaccion(function (?array $e) use ($organizacion, $proyecto, $maximo, $id, &$aceptada): ?array {
                if (! $this->vigente($e, $organizacion, $proyecto)
                    || count($e['reservas']) >= 5000
                    || $e['gastado'] + array_sum($e['reservas']) + $maximo > $e['limite']) return null;
                $e['reservas'][$id] = $maximo;
                $aceptada = true;
                return $e;
            });
        } catch (\Throwable) { return null; }
        return $aceptada ? $id : null;
    }

    public function liquidar(string $id, int $costo): void
    {
        if ($costo < 0) return;
        $this->transaccion(static function (?array $e) use ($id, $costo): ?array {
            if ($e === null || ! isset($e['reservas'][$id])) return null;
            if ($costo > $e['reservas'][$id]) $e['bloqueo'] = 'costo_superior_a_reserva';
            unset($e['reservas'][$id]);
            $e['gastado'] += $costo;
            return $e;
        });
    }

    public function bloquear(string $motivo): void
    {
        $this->transaccion(static function (?array $e) use ($motivo): ?array {
            if ($e === null) return null;
            $e['bloqueo'] = $motivo;
            return $e;
        });
    }

    public function estado(): ?array
    {
        $resultado = null;
        try {
            $this->transaccion(static function (?array $e) use (&$resultado): ?array {
                $resultado = $e;
                return null;
            });
        } catch (\Throwable) { return null; }
        return $resultado;
    }

    private function vigente(?array $e, string $organizacion, string $proyecto): bool
    {
        return $e !== null && $e['organizacion'] === $organizacion && $e['proyecto'] === $proyecto
            && $e['bloqueo'] === null && $e['vence'] > time();
    }

    private function transaccion(callable $operacion, bool $crear = false): void
    {
        if (is_link($this->ruta) || is_link($this->ruta.'.lock')
            || (! $crear && ! is_file($this->ruta))) throw new \RuntimeException('Presupuesto ausente o inseguro.');
        $lock = fopen($this->ruta.'.lock', 'c+b');
        if ($lock === false) throw new \RuntimeException('Presupuesto no accesible.');
        try {
            // Sin espera ni reintentos: otro worker ocupado conserva Qwen.
            if (! flock($lock, LOCK_EX | LOCK_NB)) throw new \RuntimeException('Presupuesto ocupado.');
            $e = null;
            if (is_file($this->ruta)) {
                if (filesize($this->ruta) > 1_000_000) throw new \RuntimeException('Presupuesto inválido.');
                $e = json_decode((string) file_get_contents($this->ruta), true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($e) || ($e['version'] ?? null) !== 1
                    || ! is_string($e['organizacion'] ?? null) || ! is_string($e['proyecto'] ?? null)
                    || ! is_int($e['vence'] ?? null) || ! is_int($e['limite'] ?? null) || $e['limite'] <= 0
                    || ! is_int($e['gastado'] ?? null) || $e['gastado'] < 0
                    || ! array_key_exists('bloqueo', $e) || ! is_array($e['reservas'] ?? null)) {
                    throw new \RuntimeException('Presupuesto inválido.');
                }
                foreach ($e['reservas'] as $reserva) {
                    if (! is_int($reserva) || $reserva <= 0) throw new \RuntimeException('Reserva inválida.');
                }
            }
            $nuevo = $operacion($e);
            if ($nuevo === null) return;
            $json = json_encode($nuevo, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            // Se escribe con el lock estable adquirido. Si una escritura falla,
            // el JSON incompleto impide nuevas llamadas, nunca restaura saldo.
            if (file_put_contents($this->ruta, $json) !== strlen($json)) throw new \RuntimeException('No se guardó el presupuesto.');
            @chmod($this->ruta, 0600);
            @chmod($this->ruta.'.lock', 0600);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
