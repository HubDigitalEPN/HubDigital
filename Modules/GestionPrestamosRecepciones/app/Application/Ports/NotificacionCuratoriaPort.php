<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

interface NotificacionCuratoriaPort
{
    public function notificarIntervencionRequerida(string $solicitudId, string $investigadorId): string;
}
