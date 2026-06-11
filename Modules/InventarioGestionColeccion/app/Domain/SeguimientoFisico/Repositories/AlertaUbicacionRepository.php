<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;

/**
 * Contrato de persistencia para las alertas de ubicación del seguimiento físico
 * (incongruencia taxonómica, tiempo de extracción excedido, etc.). La implementación
 * concreta vive en Infrastructure; el dominio solo conoce esta interfaz.
 */
interface AlertaUbicacionRepository
{
    /** Genera el siguiente identificador único para una alerta nueva. */
    public function nextIdentity(): AlertaUbicacionId;

    /** Inserta o actualiza la alerta dada. */
    public function guardar(AlertaUbicacion $alerta): void;

    /** Devuelve la alerta activa vigente de una caja, o null si no tiene ninguna abierta. */
    public function buscarActivaPorCaja(CajaId $cajaId): ?AlertaUbicacion;

    /** Recupera una alerta por su identificador, o null si no existe. */
    public function buscarPorId(AlertaUbicacionId $id): ?AlertaUbicacion;

    /**
     * Lista todas las alertas, opcionalmente filtradas por estado.
     *
     * @return AlertaUbicacion[]
     */
    public function buscarTodas(?EstadoAlerta $estado = null): array;
}
