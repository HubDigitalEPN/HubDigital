<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;

/**
 * Datos de salida tras registrar una solicitud de préstamo.
 */
final readonly class RegistrarSolicitudPrestamoOutput
{
    /**
     * @param string $solicitudId
     * @param string $numeroSolicitud
     * @param EstadoSolicitud $estado
     * @param string $tituloEstudio
     * @param string $institucionAdscripcion
     * @param string $lineaInvestigacion
     * @param string $propositoPrestamo
     * @param int $duracionPropuestaMeses
     * @param  list<array{especimen_id: ?string, especimen_codigo_externo: string, cantidad_solicitada: int}>  $items
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

    /**
     * @param SolicitudPrestamo $solicitud
     * @return self
     */
    public static function fromPrimitives(SolicitudPrestamo $solicitud): self
    {
        $items = array_map(
            fn ($item) => [
                'especimen_id'             => $item->especimenId(),
                'especimen_codigo_externo' => $item->especimenCodigoExterno(),
                'cantidad_solicitada'      => $item->cantidadSolicitada(),
            ],
            $solicitud->items()
        );

        return new self(
            solicitudId:            (string) $solicitud->id(),
            numeroSolicitud:        (string) $solicitud->codigoPrestamo(),
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
