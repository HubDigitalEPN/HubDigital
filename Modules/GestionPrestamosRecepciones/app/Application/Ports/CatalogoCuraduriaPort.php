<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

interface CatalogoCuraduriaPort
{
    /**
     * Campos DwC críticos: bloquean la carga si faltan en el Excel del depositante.
     *
     * @return string[]
     */
    public function camposCriticos(string $coleccionId): array;

    /**
     * Campos DwC recomendados: generan advertencia visual pero no bloquean la carga.
     *
     * @return string[]
     */
    public function camposRecomendados(string $coleccionId): array;
}
