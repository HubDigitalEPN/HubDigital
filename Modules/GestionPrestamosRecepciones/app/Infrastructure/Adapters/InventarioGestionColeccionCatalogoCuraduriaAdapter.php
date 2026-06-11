<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoCuraduriaPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ResolverPrioridadColumnas;

/**
 * Adapter real que consulta el sistema de prioridades de InventarioGestionColeccion
 * para determinar qué campos DwC son críticos o recomendados en la validación del Excel.
 *
 * Las prioridades son dinámicas: si el curador las modifica en la pantalla de
 * configuración de columnas, la validación del paso 5 las refleja automáticamente.
 */
/**
 * Adaptador para consultar el catálogo de curaduría de InventarioGestionColeccion.
 *
 * Implementa {@see CatalogoCuraduriaPort} utilizando el servicio de resolución de
 * prioridades de columnas del módulo de Inventario para determinar los campos
 * críticos y recomendados dinámicamente.
 */
final class InventarioGestionColeccionCatalogoCuraduriaAdapter implements CatalogoCuraduriaPort
{
    /**
     * Constructor del adaptador para consultar el catálogo de curaduría.
     *
     * @param ResolverPrioridadColumnas $resolver Servicio de resolución de prioridades de columnas.
     */
    public function __construct(
        private ResolverPrioridadColumnas $resolver,
    ) {}

    /**
     * Obtiene la lista de nombres de campos DwC considerados críticos para una colección.
     *
     * @return string[]
     */
    public function camposCriticos(string $coleccionId): array
    {
        return $this->camposDwCPorPrioridad('critica');
    }

    /**
     * Obtiene la lista de nombres de campos DwC considerados recomendados para una colección.
     *
     * @return string[]
     */
    public function camposRecomendados(string $coleccionId): array
    {
        return $this->camposDwCPorPrioridad('recomendada');
    }

    /**
     * Filtra las columnas configuradas en Inventario por su prioridad y las mapea a nombres DwC.
     *
     * @return string[]
     */
    private function camposDwCPorPrioridad(string $prioridad): array
    {
        $columnas = $this->resolver->aplicar('especimenes', RegistroColumnasEspecimen::todas());
        $mapa = RegistroColumnasEspecimen::mapaClaveADwC();

        $nombresDwC = [];
        foreach ($columnas as $col) {
            if ($col['prioridad'] !== $prioridad) {
                continue;
            }
            $dwc = $mapa[$col['clave']] ?? null;
            if ($dwc !== null) {
                $nombresDwC[] = $dwc;
            }
        }

        return array_values(array_unique($nombresDwC));
    }
}
