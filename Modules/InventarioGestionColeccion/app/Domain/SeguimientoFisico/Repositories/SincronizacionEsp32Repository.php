<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\SincronizacionEsp32;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\SincronizacionEsp32Id;

/**
 * Contrato de persistencia para los registros de sincronización del ESP32. Cada
 * registro deja constancia del último barrido reportado por el controlador de un
 * gabinete, base para calcular el estado Online/Offline del hardware IoT.
 */
interface SincronizacionEsp32Repository
{
    /** Genera el siguiente identificador único para un registro de sincronización. */
    public function nextIdentity(): SincronizacionEsp32Id;

    /** Inserta o actualiza el registro de sincronización dado. */
    public function guardar(SincronizacionEsp32 $sincronizacion): void;

    /** Devuelve la última sincronización reportada por el ESP32 de un gabinete, o null si nunca reportó. */
    public function buscarUltimaPorGabinete(GabineteId $gabineteId): ?SincronizacionEsp32;
}
