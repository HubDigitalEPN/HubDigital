<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;

final class InMemoryAlertaUbicacionRepository implements AlertaUbicacionRepository
{
    /** @var array<string, AlertaUbicacion> */
    private array $store = [];

    public function nextIdentity(): AlertaUbicacionId
    {
        return AlertaUbicacionId::generar();
    }

    public function guardar(AlertaUbicacion $alerta): void
    {
        $this->store[(string) $alerta->id()] = $alerta;
    }

    public function buscarActivaPorCaja(CajaId $cajaId): ?AlertaUbicacion
    {
        foreach ($this->store as $alerta) {
            if ($alerta->cajaId()->equals($cajaId) && $alerta->estado()->equals(EstadoAlerta::Activa)) {
                return $alerta;
            }
        }

        return null;
    }

    public function buscarPorId(AlertaUbicacionId $id): ?AlertaUbicacion
    {
        return $this->store[(string) $id] ?? null;
    }

    /** @return AlertaUbicacion[] */
    public function buscarTodas(?EstadoAlerta $estado = null): array
    {
        if ($estado === null) {
            return array_values($this->store);
        }

        return array_values(array_filter(
            $this->store,
            fn (AlertaUbicacion $a) => $a->estado()->equals($estado),
        ));
    }
}
