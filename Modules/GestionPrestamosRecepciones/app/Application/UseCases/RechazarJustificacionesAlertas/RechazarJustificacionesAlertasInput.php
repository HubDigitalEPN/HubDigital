<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas;

/**
 * Input DTO para que el curador rechace una o más justificaciones de alertas, dejando
 * la solicitud en estado "Requiere Corrección".
 */
final readonly class RechazarJustificacionesAlertasInput
{
    /**
     * @param  string[]  $justificacionesRechazadas  Tipos de alerta cuya justificación se rechaza.
     */
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public array $justificacionesRechazadas,
    ) {}
}
