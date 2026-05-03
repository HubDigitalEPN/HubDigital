<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes;

final readonly class SincronizarEspecimenesInput
{
    /**
     * @param  array<int, array{occurrenceID: string, configuracion: array<string, bool>|null}>  $especimenes
     *                                                                                                         configuracion usa claves con sufijo 'Visible' (ej: 'scientificNameVisible' => true).
     *                                                                                                         null = habilitar todos los campos por defecto.
     */
    public function __construct(
        public string $curadorId,
        public array $especimenes,
    ) {}
}
