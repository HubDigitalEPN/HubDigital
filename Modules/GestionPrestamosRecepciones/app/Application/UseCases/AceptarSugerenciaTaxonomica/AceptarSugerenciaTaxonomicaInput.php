<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica;

/**
 * Input DTO para aceptar una sugerencia taxonómica.
 */
final readonly class AceptarSugerenciaTaxonomicaInput
{
    /**
     * Crea una nueva instancia de AceptarSugerenciaTaxonomicaInput.
     * @param string $solicitudId ID de la solicitud de préstamo.
     * @param string $matrizId ID de la matriz de especies.
     * @param string $registroId ID del registro del espécimen.
     * @param string $especieCorregida Nombre científico de la especie corregida.
     */
    public function __construct(
        public string $solicitudId,
        public string $matrizId,
        public string $registroId,
        public string $especieCorregida,
    ) {}
}
