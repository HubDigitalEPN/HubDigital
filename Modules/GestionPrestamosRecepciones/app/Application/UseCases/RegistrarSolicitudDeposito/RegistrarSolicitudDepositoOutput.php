<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;

/**
 * Datos de salida tras registrar una solicitud de depósito.
 */
final readonly class RegistrarSolicitudDepositoOutput
{
    public function __construct(
        public EstadoSolicitudDeposito $estado,
        public string $id,
        public string $numero,
    ) {}

    /**
     * @param SolicitudDeposito $solicitud
     * @return self
     */
    public static function fromEntity(SolicitudDeposito $solicitud): self
    {
        return new self(
            estado: $solicitud->estado(),
            id: (string) $solicitud->id(),
            numero: (string) $solicitud->numero(),
        );
    }
}
