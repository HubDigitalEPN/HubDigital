<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoCuraduriaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarPrioridadesColumnasDwc\ConsultarPrioridadesColumnasDwcHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarPrioridadesColumnasDwc\ConsultarPrioridadesColumnasDwcInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarPrioridadesColumnasDwc\ConsultarPrioridadesColumnasDwcOutput;

/**
 * ACL entre GestionPrestamosRecepciones e InventarioGestionColeccion para el sistema de
 * prioridades de columnas: determina qué campos DwC son críticos o recomendados en la
 * validación del Excel de la matriz de especies.
 *
 * Las prioridades son dinámicas: si el curador las modifica en la pantalla de
 * configuración de columnas del inventario, la validación del paso 5 lo refleja
 * automáticamente.
 *
 * Consume el caso de uso del inventario, no sus servicios de dominio: este módulo puede
 * depender de la capa Application del inventario, nunca de su Domain.
 */
final class InventarioGestionColeccionCatalogoCuraduriaAdapter implements CatalogoCuraduriaPort
{
    public function __construct(
        private ConsultarPrioridadesColumnasDwcHandler $consultarPrioridades,
    ) {}

    /**
     * Obtiene la lista de nombres de campos DwC considerados críticos.
     *
     * @return string[]
     */
    public function camposCriticos(): array
    {
        return $this->consultar()->camposCon('critica');
    }

    /**
     * Obtiene la lista de nombres de campos DwC considerados recomendados.
     *
     * @return string[]
     */
    public function camposRecomendados(): array
    {
        return $this->consultar()->camposCon('recomendada');
    }

    /**
     * Mapa de prioridad efectiva por campo DwC, respetando overrides de BD.
     *
     * @return array<string, string> dwcKey => 'critica'|'recomendada'|'opcional'
     */
    public function prioridadesPorCampo(): array
    {
        return $this->consultar()->prioridadesPorCampoDwc;
    }

    private function consultar(): ConsultarPrioridadesColumnasDwcOutput
    {
        return $this->consultarPrioridades->handle(new ConsultarPrioridadesColumnasDwcInput);
    }
}
