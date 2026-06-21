<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarMisSolicitudes;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;

/**
 * Manejador del caso de uso para listar las solicitudes propias de un investigador,
 * con el estado del acta asociada.
 *
 * {@see ConsultarMisSolicitudesInput}
 * {@see ConsultarMisSolicitudesOutput}
 */
final class ConsultarMisSolicitudesHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     */
    public function handle(ConsultarMisSolicitudesInput $input): ConsultarMisSolicitudesOutput
    {
        $filas = $this->solicitudRepo->listarPorInvestigador(
            investigadorId: $input->investigadorId,
            estado: $input->estado,
            busquedaTexto: $input->busqueda,
            ordenCampo: $input->ordenCampo,
            ordenDireccion: $input->ordenDireccion,
        );

        $filasVista = array_map(
            static fn (array $fila): FilaMiSolicitud => new FilaMiSolicitud(
                solicitudId: $fila['solicitudId'],
                numeroSolicitud: $fila['numeroSolicitud'],
                tituloEstudio: $fila['tituloEstudio'],
                estado: $fila['estado'],
                fecha: $fila['fecha'],
                actaId: $fila['actaId'],
                actaEstado: $fila['actaEstado'],
            ),
            $filas,
        );

        return new ConsultarMisSolicitudesOutput(filas: $filasVista);
    }
}
