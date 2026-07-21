<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarPrioridadesColumnasDwc;

final readonly class ConsultarPrioridadesColumnasDwcOutput
{
    /**
     * @param  array<string, string>  $prioridadesPorCampoDwc  nombre DwC => 'critica'|'recomendada'|'opcional'
     */
    public function __construct(
        public array $prioridadesPorCampoDwc,
    ) {}

    /**
     * Nombres DwC con la prioridad indicada.
     *
     * @return string[]
     */
    public function camposCon(string $prioridad): array
    {
        return array_values(array_keys(
            array_filter($this->prioridadesPorCampoDwc, fn (string $p): bool => $p === $prioridad)
        ));
    }
}
