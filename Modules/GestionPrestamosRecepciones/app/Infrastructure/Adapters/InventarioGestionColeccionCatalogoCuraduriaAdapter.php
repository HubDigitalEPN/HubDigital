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
final class InventarioGestionColeccionCatalogoCuraduriaAdapter implements CatalogoCuraduriaPort
{
    public function __construct(
        private ResolverPrioridadColumnas $resolver,
    ) {}

    public function camposCriticos(string $coleccionId): array
    {
        return $this->camposDwCPorPrioridad('critica');
    }

    public function camposRecomendados(string $coleccionId): array
    {
        return $this->camposDwCPorPrioridad('recomendada');
    }

    /** @return string[] */
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
