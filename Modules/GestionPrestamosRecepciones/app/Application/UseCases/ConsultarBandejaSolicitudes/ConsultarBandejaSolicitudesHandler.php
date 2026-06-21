<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaSolicitudes;

use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;

/**
 * Manejador del caso de uso para consultar la bandeja de solicitudes del curador,
 * aplicando filtros, orden y resolución de nombres de solicitantes.
 *
 * {@see ConsultarBandejaSolicitudesInput}
 * {@see ConsultarBandejaSolicitudesOutput}
 */
final class ConsultarBandejaSolicitudesHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly UsuarioNombrePort $usuarios,
    ) {}

    /**
     * Ejecuta el caso de uso.
     */
    public function handle(ConsultarBandejaSolicitudesInput $input): ConsultarBandejaSolicitudesOutput
    {
        $investigadorIds = $input->busquedaInvestigador !== ''
            ? $this->usuarios->buscarIdsPorNombre($input->busquedaInvestigador)
            : null;

        $filas = $this->solicitudRepo->listarParaBandeja(
            investigadorIds: $investigadorIds,
            estado: $input->estado,
            busquedaTexto: $input->busqueda,
            ordenCampo: $input->ordenCampo,
            ordenDireccion: $input->ordenDireccion,
        );

        $idsSolicitantes = array_values(array_unique(array_filter(
            array_map(static fn (array $fila): ?string => $fila['investigadorId'], $filas),
        )));

        $nombres = $this->usuarios->obtenerNombres($idsSolicitantes);

        $filasVista = array_map(
            fn (array $fila): FilaSolicitudBandeja => new FilaSolicitudBandeja(
                solicitudId: $fila['solicitudId'],
                numeroSolicitud: $fila['numeroSolicitud'],
                tituloEstudio: $fila['tituloEstudio'],
                investigadorId: $fila['investigadorId'],
                solicitanteNombre: $fila['investigadorId'] !== null
                    ? ($nombres[$fila['investigadorId']] ?? null)
                    : null,
                estado: $fila['estado'],
                fecha: $fila['fecha'],
            ),
            $filas,
        );

        return new ConsultarBandejaSolicitudesOutput(filas: $filasVista);
    }
}
