<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventoHistorialDto;

/**
 * Output DTO para el caso de uso de consultar el historial de una solicitud.
 */
final readonly class ConsultarHistorialSolicitudOutput
{
    /**
     * @param string $solicitudId ID de la solicitud.
     * @param EventoHistorialOutput[] $eventos Lista de eventos del historial.
     */
    public function __construct(
        public string $solicitudId,
        public array $eventos,
    ) {}

    /**
     * Crea una instancia de salida a partir de los eventos del historial.
     *
     * @param string $solicitudId ID de la solicitud.
     * @param EventoHistorialDto[] $dtos Eventos del historial.
     * @return self
     */
    public static function fromEventos(string $solicitudId, array $dtos): self
    {
        return new self(
            solicitudId: $solicitudId,
            eventos: array_map(
                fn (EventoHistorialDto $dto) => new EventoHistorialOutput(
                    tipo: $dto->tipo,
                    ocurridoEn: $dto->ocurridoEn,
                    datos: $dto->datos,
                ),
                $dtos,
            ),
        );
    }
}

/**
 * Representa un evento en el historial de una solicitud.
 */
final readonly class EventoHistorialOutput
{
    /**
     * @param string $tipo Tipo de evento.
     * @param DateTimeImmutable $ocurridoEn Fecha y hora en que ocurrió el evento.
     * @param array $datos Datos adicionales asociados al evento.
     */
    public function __construct(
        public string $tipo,
        public DateTimeImmutable $ocurridoEn,
        public array $datos,
    ) {}
}
