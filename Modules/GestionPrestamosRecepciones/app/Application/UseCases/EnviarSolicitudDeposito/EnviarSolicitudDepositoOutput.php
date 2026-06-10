<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;

/**
 * Output DTO para el caso de uso de enviar una solicitud de depósito a revisión.
 */
final readonly class EnviarSolicitudDepositoOutput
{
    /**
     * @param EstadoSolicitudDeposito $estado Estado actualizado de la solicitud.
     */
    public function __construct(
        public EstadoSolicitudDeposito $estado,
    ) {}

    /**
     * Crea una instancia de salida a partir de una entidad SolicitudDeposito.
     *
     * @param SolicitudDeposito $solicitud Entidad de solicitud de depósito.
     * @return self
     */
    public static function fromEntity(SolicitudDeposito $solicitud): self
    {
        return new self(
            estado: $solicitud->estado(),
        );
    }
}
