<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32;

/**
 * DTO de salida con el resultado del evento procesado (caja, ranura, estado y alertas/notificaciones).
 */
final readonly class ProcesarEventoEsp32Output
{
    public function __construct(
        public string $cajaId,
        public string $ranuraId,
        public string $estadoCaja,
        public bool $alertaGenerada,
        public bool $notificacionEnviada,
    ) {}
}
