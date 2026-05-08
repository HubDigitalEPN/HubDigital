<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;

final readonly class ActualizarSolicitudPrestamoOutput
{
    /**
     * @param  list<array{especimen_codigo_externo: string, cantidad_solicitada: int}>  $items
     */
    public function __construct(
        public string $solicitudId,
        public string $numeroSolicitud,
        public EstadoSolicitud $estado,
        public string $tituloEstudio,
        public string $institucionAdscripcion,
        public string $lineaInvestigacion,
        public string $propositoPrestamo,
        public int $duracionPropuestaMeses,
        public array $items,
    ) {}

    public static function fromPrimitives(SolicitudPrestamo $solicitud): self
    {
        $items = array_map(
            fn ($item) => [
                'especimen_codigo_externo' => $item->especimenCodigoExterno(),
                'cantidad_solicitada'      => $item->cantidadSolicitada(),
            ],
            $solicitud->items()
        );

        return new self(
            solicitudId:            (string) $solicitud->id(),
            numeroSolicitud:        (string) $solicitud->numeroSolicitud(),
            estado:                 $solicitud->estado(),
            tituloEstudio:          $solicitud->tituloEstudio() ?? '',
            institucionAdscripcion: $solicitud->institucionAdscripcion() ?? '',
            lineaInvestigacion:     $solicitud->lineaInvestigacion() ?? '',
            propositoPrestamo:      $solicitud->propositoPrestamo() ?? '',
            duracionPropuestaMeses: $solicitud->duracionPropuestaMeses() ?? 0,
            items:                  $items,
        );
    }
}
