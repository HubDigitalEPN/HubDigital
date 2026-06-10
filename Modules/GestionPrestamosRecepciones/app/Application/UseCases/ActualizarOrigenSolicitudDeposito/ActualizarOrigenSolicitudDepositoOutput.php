<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;

/**
 * Output DTO para la actualización del origen de una solicitud de depósito.
 */
final readonly class ActualizarOrigenSolicitudDepositoOutput
{
    /**
     * Crea una nueva instancia de ActualizarOrigenSolicitudDepositoOutput.
     * @param EstadoSolicitudDeposito $estado Estado actual de la solicitud.
     */
    public function __construct(
        public EstadoSolicitudDeposito $estado,
    ) {}

    /**
     * Crea una instancia a partir de la entidad de solicitud de depósito.
     * @param SolicitudDeposito $solicitud
     * @return self
     */
    public static function fromEntity(SolicitudDeposito $solicitud): self
    {
        return new self(estado: $solicitud->estado());
    }
}
