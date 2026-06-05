<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventoHistorialDto;

final readonly class ConsultarHistorialPrestamoOutput
{
    /** @param EventoHistorialPrestamoOutput[] $eventos */
    public function __construct(
        public string $prestamoId,
        public array $eventos,
    ) {}

    /** @param EventoHistorialDto[] $dtos */
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

final readonly class EventoHistorialPrestamoOutput
{
    public function __construct(
        public string $tipo,
        public DateTimeImmutable $ocurridoEn,
        public array $datos,
    ) {}
}
