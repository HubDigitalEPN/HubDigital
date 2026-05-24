<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventoHistorialDto;

final readonly class ConsultarHistorialSolicitudOutput
{
    /** @param EventoHistorialOutput[] $eventos */
    public function __construct(
        public string $solicitudId,
        public array $eventos,
    ) {}

    /** @param EventoHistorialDto[] $dtos */
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

final readonly class EventoHistorialOutput
{
    public function __construct(
        public string $tipo,
        public DateTimeImmutable $ocurridoEn,
        public array $datos,
    ) {}
}
