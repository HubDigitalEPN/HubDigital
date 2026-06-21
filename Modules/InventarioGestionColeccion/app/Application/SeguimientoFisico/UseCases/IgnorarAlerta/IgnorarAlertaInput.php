<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IgnorarAlerta;

/**
 * DTO de entrada con la alerta que se quiere ignorar.
 */
final readonly class IgnorarAlertaInput
{
    public function __construct(public string $alertaId) {}
}
