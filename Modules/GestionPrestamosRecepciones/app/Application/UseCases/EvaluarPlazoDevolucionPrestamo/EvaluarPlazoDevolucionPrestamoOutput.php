<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecordatorio;

final readonly class EvaluarPlazoDevolucionPrestamoOutput
{
    public function __construct(
        public bool $notificacionEnviada,
        public ?string $estadoRecordatorio,
    ) {}

    public static function from(bool $notificacionEnviada, ?EstadoRecordatorio $estado): self
    {
        return new self(
            notificacionEnviada: $notificacionEnviada,
            estadoRecordatorio: $estado?->value,
        );
    }
}
