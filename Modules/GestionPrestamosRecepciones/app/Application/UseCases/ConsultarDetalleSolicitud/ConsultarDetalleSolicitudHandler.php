<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud;

use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ItemPrestamoVista;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Manejador del caso de uso para consultar el detalle completo de una solicitud de
 * préstamo (datos del estudio + ítems), listo para renderizar.
 *
 * {@see ConsultarDetalleSolicitudInput}
 * {@see ConsultarDetalleSolicitudOutput}
 */
final class ConsultarDetalleSolicitudHandler
{
    /**
     * @param SolicitudPrestamoRepositoryInterface $solicitudRepo Repositorio de solicitudes de préstamo.
     */
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarDetalleSolicitudInput $input Datos de entrada.
     * @return ConsultarDetalleSolicitudOutput|null Detalle de la solicitud, o null si no existe.
     */
    public function handle(ConsultarDetalleSolicitudInput $input): ?ConsultarDetalleSolicitudOutput
    {
        $solicitud = $this->solicitudRepo->buscarPorId(SolicitudPrestamoId::fromString($input->solicitudId));

        if ($solicitud === null) {
            return null;
        }

        $items = array_values(array_map(
            fn (ItemPrestamo $item) => new ItemPrestamoVista(
                itemPrestamoId: (string) $item->id(),
                codigoExterno: $item->especimenCodigoExterno(),
                cantidadSolicitada: $item->cantidadSolicitada(),
                nombre: $item->especimenSnapshot()['nombre_cientifico'] ?? null,
                condicionesEspecificas: $item->condicionesEspecificas(),
                especimenId: $item->especimenId(),
                individualesDisponibles: $item->especimenSnapshot()['individuales_disponibles'] ?? null,
            ),
            $solicitud->items(),
        ));

        return new ConsultarDetalleSolicitudOutput(
            solicitudId: (string) $solicitud->id(),
            investigadorId: $solicitud->investigadorId(),
            numeroSolicitud: (string) $solicitud->codigoPrestamo(),
            estado: $solicitud->estado()->value,
            tituloEstudio: $solicitud->tituloEstudio(),
            institucionAdscripcion: $solicitud->institucionAdscripcion(),
            lineaInvestigacion: $solicitud->lineaInvestigacion(),
            duracionPropuestaMeses: $solicitud->duracionPropuestaMeses(),
            alcancePrestamo: $solicitud->alcancePrestamo()->value,
            propositoPrestamo: $solicitud->propositoPrestamo(),
            justificacionExtendida: $solicitud->justificacionExtendida(),
            comentarioCurador: $solicitud->comentarioCurador(),
            items: $items,
        );
    }
}
