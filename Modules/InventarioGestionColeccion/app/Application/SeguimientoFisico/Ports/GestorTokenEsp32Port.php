<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

/**
 * Gestiona el token de API personal con el que el ESP32 de un gabinete autentica
 * sus eventos. Aísla a la presentación del mecanismo concreto de emisión/revocación
 * de tokens (Sanctum), que es un detalle de infraestructura.
 */
interface GestorTokenEsp32Port
{
    /** Indica si ya existe un token de API para el ESP32 del gabinete dado. */
    public function existeToken(string $gabineteId): bool;

    /**
     * Revoca el token anterior del ESP32 del gabinete y emite uno nuevo,
     * devolviendo su valor en claro una sola vez para mostrarlo al curador.
     */
    public function regenerarToken(string $gabineteId): string;
}
