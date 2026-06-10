<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use DateTimeImmutable;

/**
 * DTO de la capa de aplicación que representa una entrada del historial de eventos de
 * una solicitud o préstamo, tal como la devuelve {@see HistorialPort}.
 */
final readonly class EventoHistorialDto
{
    /**
     * @param  string  $tipo  Nombre/tipo del evento de dominio.
     * @param  array<string, mixed>  $datos  Carga útil específica del evento.
     */
    public function __construct(
        public string $tipo,
        public DateTimeImmutable $ocurridoEn,
        public array $datos = [],
    ) {}
}
