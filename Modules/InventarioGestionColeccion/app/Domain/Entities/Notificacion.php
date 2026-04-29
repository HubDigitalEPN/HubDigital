<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Entities;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\NotificacionId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\TipoNotificacion;

class Notificacion
{
    private function __construct(
        private readonly NotificacionId $id,
        private readonly CajaId $cajaId,
        private readonly TipoNotificacion $tipo,
        private readonly array $datosContexto,
    ) {}

    public static function crear(
        NotificacionId $id,
        CajaId $cajaId,
        TipoNotificacion $tipo,
        array $datosContexto = [],
    ): self {
        return new self(
            id: $id,
            cajaId: $cajaId,
            tipo: $tipo,
            datosContexto: $datosContexto,
        );
    }

    public function tipo(): string
    {
        return $this->tipo->valor();
    }

    public function id(): NotificacionId
    {
        return $this->id;
    }

    public function cajaId(): CajaId
    {
        return $this->cajaId;
    }

    public function tipoNotificacion(): TipoNotificacion
    {
        return $this->tipo;
    }

    public function datosContexto(): array
    {
        return $this->datosContexto;
    }
}
