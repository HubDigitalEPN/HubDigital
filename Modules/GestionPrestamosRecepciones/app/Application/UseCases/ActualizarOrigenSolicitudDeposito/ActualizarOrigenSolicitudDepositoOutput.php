<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;

final readonly class ActualizarOrigenSolicitudDepositoOutput
{
    public function __construct(
        public EstadoSolicitudDeposito $estado,
    ) {}

    public static function fromEntity(SolicitudDeposito $solicitud): self
    {
        return new self(estado: $solicitud->estado());
    }
}
