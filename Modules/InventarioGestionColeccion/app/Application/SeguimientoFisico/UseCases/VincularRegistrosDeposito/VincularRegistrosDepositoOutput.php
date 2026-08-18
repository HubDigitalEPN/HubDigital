<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VincularRegistrosDeposito;

/**
 * Resultado del backfill del vínculo.
 *
 * `discrepancias` es lo importante: si el orden reconstruido de la matriz no coincide
 * con lo que se ingresó, no se escribe nada y aquí queda dicho por qué.
 *
 * @param  array<string, string>  $especimenPorRegistro  registroId => especimenId
 * @param  string[]  $discrepancias
 */
final readonly class VincularRegistrosDepositoOutput
{
    public function __construct(
        public int $vinculados,
        public int $yaVinculados,
        public int $sinEspecimen,
        public array $especimenPorRegistro,
        public array $discrepancias,
        public bool $simulado,
    ) {}

    public function esConsistente(): bool
    {
        return $this->discrepancias === [];
    }
}
