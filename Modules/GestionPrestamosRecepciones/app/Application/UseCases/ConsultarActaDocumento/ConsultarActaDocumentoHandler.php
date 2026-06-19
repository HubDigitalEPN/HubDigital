<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento;

use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ItemPrestamoVista;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Manejador del caso de uso para consultar el documento completo de un acta de
 * préstamo (acta + solicitud + ítems), listo para renderizar.
 *
 * {@see ConsultarActaDocumentoInput}
 * {@see ConsultarActaDocumentoOutput}
 */
final class ConsultarActaDocumentoHandler
{
    /**
     * @param ActaPrestamoRepositoryInterface $actaRepo Repositorio de actas de préstamo.
     * @param SolicitudPrestamoRepositoryInterface $solicitudRepo Repositorio de solicitudes de préstamo.
     */
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarActaDocumentoInput $input Datos de entrada.
     * @return ConsultarActaDocumentoOutput|null Documento del acta, o null si no existe.
     */
    public function handle(ConsultarActaDocumentoInput $input): ?ConsultarActaDocumentoOutput
    {
        $acta = $this->actaRepo->buscarPorId(ActaPrestamoId::fromString($input->actaId));

        if ($acta === null) {
            return null;
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());

        $items = $solicitud === null ? [] : array_values(array_map(
            fn (ItemPrestamo $item) => new ItemPrestamoVista(
                itemPrestamoId: (string) $item->id(),
                codigoExterno: $item->especimenCodigoExterno(),
                cantidadSolicitada: $item->cantidadSolicitada(),
                nombre: $item->especimenSnapshot()['nombre'] ?? null,
                condicionesEspecificas: $item->condicionesEspecificas(),
            ),
            $solicitud->items(),
        ));

        return new ConsultarActaDocumentoOutput(
            id: (string) $acta->id(),
            investigadorId: $solicitud?->investigadorId() ?? '',
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            tipoPrestamo: $acta->tipoPrestamo()->value,
            alcancePrestamo: $acta->alcancePrestamo()->value,
            fechaInicio: $acta->fechaInicio(),
            fechaFin: $acta->fechaFin(),
            condicionesGenerales: $acta->condicionesGenerales(),
            numeroSolicitud: $solicitud !== null ? (string) $solicitud->numeroSolicitud() : null,
            tituloEstudio: $solicitud?->tituloEstudio(),
            institucionAdscripcion: $solicitud?->institucionAdscripcion(),
            lineaInvestigacion: $solicitud?->lineaInvestigacion(),
            propositoPrestamo: $solicitud?->propositoPrestamo(),
            items: $items,
        );
    }
}
