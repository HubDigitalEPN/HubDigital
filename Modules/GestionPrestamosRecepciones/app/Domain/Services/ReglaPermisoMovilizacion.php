<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoDocumental;

/**
 * Servicio de dominio puro que determina si una solicitud cumple con el requisito
 * del permiso de movilización según su provincia de origen.
 *
 * Las provincias exentas (p. ej. Pichincha) y los orígenes del exterior no
 * requieren el permiso; en el resto de casos el documento debe estar adjunto.
 */
final class ReglaPermisoMovilizacion
{
    private const NOMBRE_DOCUMENTO = 'Copia del permiso de movilización';

    /** Provincias que no requieren Permiso de Movilización */
    private const PROVINCIAS_EXENTAS = ['Pichincha'];

    /**
     * Evalúa si el requisito del permiso de movilización está satisfecho.
     *
     * @param  string|null  $provinciaOrigen  Provincia de recolección; null para origen exterior.
     * @param  string[]  $nombresDocumentosAdjuntos  Nombres de los documentos adjuntos a la solicitud.
     * @return EstadoDocumental Valido si no aplica o está adjunto; RequiereCorreccion en caso contrario.
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
