<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoDocumental;

final class ReglaPermisoMovilizacion
{
    private const NOMBRE_DOCUMENTO = 'Copia del Permiso de Movilización';

    /** Provincias que no requieren Permiso de Movilización */
    private const PROVINCIAS_EXENTAS = ['Pichincha'];

    /**
     * @param  string[]  $nombresDocumentosAdjuntos
     */
    public function validar(?string $provinciaOrigen, array $nombresDocumentosAdjuntos): EstadoDocumental
    {
        // Sin provincia → origen exterior, no aplica permiso de movilización
        if (empty($provinciaOrigen)) {
            return EstadoDocumental::Valido;
        }

        if (in_array($provinciaOrigen, self::PROVINCIAS_EXENTAS, true)) {
            return EstadoDocumental::Valido;
        }

        if (in_array(self::NOMBRE_DOCUMENTO, $nombresDocumentosAdjuntos, true)) {
            return EstadoDocumental::Valido;
        }

        return EstadoDocumental::RequiereCorreccion;
    }
}
