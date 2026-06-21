<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ResultadoTiempoExtraccion;

/**
 * Evalúa cuánto tiempo lleva una caja fuera de su posición y lo clasifica
 * respecto al límite de extracción.
 *
 * - Dentro del límite: aún no requiere atención.
 * - Próxima al límite: ha consumido al menos el umbral preventivo del límite → notificación preventiva.
 * - Excedida: alcanzó o superó el límite → alerta de extracción prolongada.
 *
 * Nota: el cálculo usa horas de reloj. Tratar "día hábil" excluyendo fines de semana
 * es una refinación futura; hoy 1 día hábil ≈ 24 horas.
 */
final class EvaluadorTiempoExtraccion
{
    /**
     * Clasifica el tiempo que una caja lleva fuera de su posición comparando las horas
     * transcurridas desde su retiro contra el límite y un umbral preventivo (75 % por
     * defecto): dentro del límite, próxima al límite o excedida.
     */
    public function evaluar(
        \DateTimeImmutable $retiradaEn,
        \DateTimeImmutable $ahora,
        int $limiteHoras,
        float $umbralPreventivo = 0.75,
    ): ResultadoTiempoExtraccion {
        $horasFuera = ($ahora->getTimestamp() - $retiradaEn->getTimestamp()) / 3600;

        if ($horasFuera >= $limiteHoras) {
            return ResultadoTiempoExtraccion::Excedida;
        }

        if ($horasFuera >= $limiteHoras * $umbralPreventivo) {
            return ResultadoTiempoExtraccion::ProximaAlLimite;
        }

        return ResultadoTiempoExtraccion::DentroDelLimite;
    }
}
