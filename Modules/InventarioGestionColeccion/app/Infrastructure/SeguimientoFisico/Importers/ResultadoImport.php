<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

/**
 * Reporte agregado de una corrida del importador.
 *
 * Inmutable. El orchestrator construye uno final con todos los conteos.
 */
final readonly class ResultadoImport
{
    /**
     * @param  array<string, int>  $motivosRevision  Diccionario motivo → conteo.
     * @param  array<int, array{fila: int, error: string}>  $erroresFatales  Filas que NO se pudieron persistir.
     */
    public function __construct(
        public int $filasLeidas,
        public int $especimenesPersistidos,
        public int $muestrasCreadas,
        public int $duplicadosSaltados,
        public int $marcadosParaRevision,
        public array $motivosRevision,
        public array $erroresFatales,
        public bool $dryRun,
    ) {}

    public function resumenLinea(): string
    {
        $modo = $this->dryRun ? 'DRY-RUN' : 'PERSISTIDO';

        return "[{$modo}] {$this->filasLeidas} filas leídas, ".
            "{$this->especimenesPersistidos} especímenes, ".
            "{$this->muestrasCreadas} muestras, ".
            "{$this->duplicadosSaltados} duplicados saltados, ".
            "{$this->marcadosParaRevision} para revisión, ".
            count($this->erroresFatales).' errores fatales';
    }
}
