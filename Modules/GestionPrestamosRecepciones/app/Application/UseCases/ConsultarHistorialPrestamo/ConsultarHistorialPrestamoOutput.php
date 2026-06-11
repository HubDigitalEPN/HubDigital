<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventoHistorialDto;

/**
 * Output DTO para el caso de uso de consultar el historial de un préstamo.
 */
final readonly class ConsultarHistorialPrestamoOutput
{
    /**
     * @param string $prestamoId ID del préstamo.
     * @param EventoHistorialPrestamoOutput[] $eventos Lista de eventos del historial.
     */
    public function __construct(
        public string $prestamoId,
        public array $eventos,
    ) {}

    /**
     * Crea una instancia de salida a partir de los eventos del historial.
     * 
     * @param string $prestamoId ID del préstamo.
     * @param EventoHistorialDto[] $dtos Eventos del historial.
     * @return self
     */
    public static function fromEventos(string $prestamoId, array $dtos): self
    {
        return new self(
            prestamoId: $prestamoId,
            eventos: array_map(
                fn (EventoHistorialDto $dto) => new EventoHistorialPrestamoOutput(
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
 * Representa un evento en el historial de un préstamo.
 */
final readonly class EventoHistorialPrestamoOutput
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
