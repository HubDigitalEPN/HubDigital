<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies;

final readonly class CargarMatrizEspeciesInput
{
    /**
     * @param  array<string, mixed>  $camposDwCPresentes  Campos DwC presentes en la matriz cargada
     * @param  string[]  $camposCriticos  Campos que bloquean la carga si faltan
     * @param  string[]  $camposRecomendados  Campos que generan advertencia si faltan
     * @param  array<int, array<string, mixed>>  $registros  Registros de especímenes a cargar
     */
    public function __construct(
        public string $solicitudId,
        public array $camposDwCPresentes,
        public array $camposCriticos,
        public array $camposRecomendados,
        public array $registros,
    ) {}
}
