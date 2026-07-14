<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarSincronizacionEsp32;

/**
 * DTO de salida con el resumen de la reconciliación del barrido.
 */
final readonly class ProcesarSincronizacionEsp32Output
{
    /**
     * @param  int  $ingresos  Cajas que pasaron de tránsito/extracción a EnGabinete.
     * @param  int  $retiros  Cajas que pasaron a EnTransito porque el barrido las reportó ausentes.
     * @param  int  $reubicaciones  Cajas movidas a una ranura distinta de la que figuraba en BD.
     * @param  int  $sinCambios  Ranuras cuyo estado coincidía con el barrido (sin escritura de dominio).
     * @param  string[]  $tagsDesconocidos  UIDs del barrido que no mapean a ninguna caja registrada.
     * @param  int[]  $slotsDesconocidos  slot_index del barrido que no corresponden a ranuras configuradas.
     * @param  int  $alertasGeneradas  Total de alertas de dominio generadas (movimiento fuera de horario + taxonómicas).
     * @param  bool  $conIncongruencias  True si alguna lectura del barrido resultó incongruente taxonómicamente.
     * @param  string  $sincronizacionId  UUID de la SincronizacionEsp32 persistida para este barrido.
     */
    public function __construct(
        public int $ingresos,
        public int $retiros,
        public int $reubicaciones,
        public int $sinCambios,
        public array $tagsDesconocidos,
        public array $slotsDesconocidos,
        public int $alertasGeneradas,
        public bool $conIncongruencias,
        public string $sincronizacionId,
    ) {}
}
