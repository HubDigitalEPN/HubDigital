<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecordatorio;

/**
 * Output DTO para el caso de uso de evaluar el plazo de devolución.
 */
final readonly class EvaluarPlazoDevolucionPrestamoOutput
{
    /**
     * @param bool $notificacionEnviada Indica si se envió alguna notificación/recordatorio.
     * @param string|null $estadoRecordatorio Estado del recordatorio si fue generado.
     */
    public function __construct(
        public bool $notificacionEnviada,
        public ?string $estadoRecordatorio,
    ) {}

    /**
     * Crea una instancia a partir del estado del recordatorio.
     *
     * @param bool $notificacionEnviada Indica si la notificación fue enviada.
     * @param EstadoRecordatorio|null $estado Estado del recordatorio.
     * @return self
     */
    public static function from(bool $notificacionEnviada, ?EstadoRecordatorio $estado): self
    {
        return new self(
            notificacionEnviada: $notificacionEnviada,
            estadoRecordatorio: $estado?->value,
        );
    }
}
