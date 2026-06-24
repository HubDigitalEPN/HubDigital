<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ReiterarRecordatorioVencimiento;

/**
 * Output DTO para el reenvío del recordatorio de vencimiento.
 */
final readonly class ReiterarRecordatorioVencimientoOutput
{
    /**
     * @param bool $notificado Indica si se reenvió el recordatorio de vencido.
     */
    public function __construct(
        public bool $notificado,
    ) {}

    public static function from(bool $notificado): self
    {
        return new self(notificado: $notificado);
    }
}
