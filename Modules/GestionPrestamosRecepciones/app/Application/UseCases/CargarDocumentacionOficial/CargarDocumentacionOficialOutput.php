<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;

final readonly class CargarDocumentacionOficialOutput
{
    public function __construct(
        public EstadoSolicitudDeposito $estado,
        public ?string $nombreInvestigadorDocumento,
    ) {}

    public static function fromEntity(SolicitudDeposito $solicitud): self
    {
        return new self(
            estado: $solicitud->estado(),
            nombreInvestigadorDocumento: $solicitud->nombreInvestigadorDocumento(),
        );
    }
}
