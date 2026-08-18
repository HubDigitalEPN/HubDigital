<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables;

/**
 * @param  EspecimenPrestableDto[]  $especimenes
 */
final readonly class ConsultarEspecimenesPrestablesOutput
{
    public function __construct(
        public array $especimenes,
    ) {}

    /** @return array<string, EspecimenPrestableDto> indexado por identificador */
    public function porId(): array
    {
        $salida = [];

        foreach ($this->especimenes as $especimen) {
            $salida[$especimen->especimenId] = $especimen;
        }

        return $salida;
    }

    public function primero(): ?EspecimenPrestableDto
    {
        return $this->especimenes[0] ?? null;
    }
}
