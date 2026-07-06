<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones;

/** Output de la aceptación con observaciones: estado del lote, registro en acta, estado de colección y notificación. */
final readonly class AceptarRecepcionConObservacionesOutput
{
    public function __construct(
        public string $estado,
        public bool $observacionesRegistradasEnActa,
        public string $estadoColeccion,
        public bool $notificacionInvestigadorEnviada,
    ) {}

    public static function fromPrimitives(
        string $estado,
        bool $observacionesRegistradasEnActa,
        string $estadoColeccion,
        bool $notificacionInvestigadorEnviada,
    ): self {
        return new self(
            estado: $estado,
            observacionesRegistradasEnActa: $observacionesRegistradasEnActa,
            estadoColeccion: $estadoColeccion,
            notificacionInvestigadorEnviada: $notificacionInvestigadorEnviada,
        );
    }
}
