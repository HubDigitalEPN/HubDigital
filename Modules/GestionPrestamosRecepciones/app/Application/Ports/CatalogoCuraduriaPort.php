<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para consultar la configuración de campos Darwin
 * Core (DwC) que exige una colección del módulo de inventario.
 *
 * Lo implementa un adaptador en Infrastructure que consulta el módulo de gestión de
 * colecciones, respetando el aislamiento entre bounded contexts.
 */
interface CatalogoCuraduriaPort
{
    /**
     * Campos DwC críticos: bloquean la carga si faltan en el Excel del depositante.
     *
     * @return string[]
     */
    public function camposCriticos(): array;

    /**
     * Campos DwC recomendados: generan advertencia visual pero no bloquean la carga.
     *
     * @return string[]
     */
    public function camposRecomendados(): array;

    /**
     * Mapa de prioridad efectiva por campo DwC (respeta los overrides de BD del
     * curador). Útil para filtrar/agrupar columnas por prioridad en la revisión.
     *
     * @return array<string, string> dwcKey => 'critica'|'recomendada'|'opcional'
     */
    public function prioridadesPorCampo(): array;
}
