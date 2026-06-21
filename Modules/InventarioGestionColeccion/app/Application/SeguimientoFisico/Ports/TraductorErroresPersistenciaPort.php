<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

use Throwable;

/**
 * Traduce errores de persistencia (violaciones de constraint, fallos de conexión)
 * a un mensaje legible para el usuario. Mantiene el conocimiento del motor de base
 * de datos —SQLSTATE, nombres de constraint, tipos de excepción— dentro de la
 * infraestructura, fuera de la capa de presentación.
 */
interface TraductorErroresPersistenciaPort
{
    /**
     * Devuelve un mensaje legible si el error proviene de la base de datos, o null
     * si no es un error de persistencia (y la presentación debe tratarlo de otro modo).
     */
    public function mensajeAmigable(Throwable $e): ?string;
}
