<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarPrioridadesColumnasDwc;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ResolverPrioridadColumnas;

/**
 * Caso de uso: expone la prioridad efectiva de cada campo Darwin Core de especímenes,
 * ya resueltos los overrides que el curador haya configurado en pantalla.
 *
 * Existe para que otros módulos (GestionPrestamosRecepciones valida contra estas
 * prioridades la matriz de especies del Excel) no tengan que importar los servicios de
 * dominio del inventario. Es el punto de entrada público: el Domain queda dentro.
 */
final class ConsultarPrioridadesColumnasDwcHandler
{
    private const PANTALLA = 'especimenes';

    public function __construct(
        private readonly ResolverPrioridadColumnas $resolver,
    ) {}

    public function handle(ConsultarPrioridadesColumnasDwcInput $input): ConsultarPrioridadesColumnasDwcOutput
    {
        $columnas = $this->resolver->aplicar(self::PANTALLA, RegistroColumnasEspecimen::todas());
        $mapa = RegistroColumnasEspecimen::mapaClaveADwC();

        $prioridades = [];

        foreach ($columnas as $columna) {
            $dwc = $mapa[$columna['clave']] ?? null;

            if ($dwc !== null) {
                $prioridades[$dwc] = $columna['prioridad'];
            }
        }

        return new ConsultarPrioridadesColumnasDwcOutput(prioridadesPorCampoDwc: $prioridades);
    }
}
