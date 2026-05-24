<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies;

final readonly class CargarMatrizEspeciesInput
{
    /**
     * @param  array<string, mixed>  $camposDwCPresentes  Campos DwC presentes en la matriz cargada
     * @param  string[]  $camposDwCExigidosPorCatalogo  Campos exigidos por el catálogo de curaduría
     * @param  array<int, array<string, mixed>>  $registros  Registros de especímenes a cargar
     */
    public function __construct(
        public string $solicitudId,
        public array $camposDwCPresentes,
        public array $camposDwCExigidosPorCatalogo,
        public array $registros,
    ) {}
}
