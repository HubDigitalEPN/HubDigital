<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas;

/**
 * Output DTO tras rechazar justificaciones: estado resultante, comentario para el
 * investigador y notificación enviada.
 */
final readonly class RechazarJustificacionesAlertasOutput
{
    public function __construct(
        public string $estado,
        public ?string $comentarioInvestigador,
        public bool $notificacionInvestigadorEnviada,
    ) {}

    public static function fromPrimitives(
        string $estado,
        ?string $comentarioInvestigador,
        bool $notificacionInvestigadorEnviada,
    ): self {
        return new self(
            estado: $estado,
            comentarioInvestigador: $comentarioInvestigador,
            notificacionInvestigadorEnviada: $notificacionInvestigadorEnviada,
        );
    }
}
