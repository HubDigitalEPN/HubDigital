<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarJustificacionesAlertas;

/**
 * Input DTO para que el curador acepte todas las justificaciones pendientes y apruebe
 * la solicitud.
 */
final readonly class AceptarJustificacionesAlertasInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
    ) {}
}
