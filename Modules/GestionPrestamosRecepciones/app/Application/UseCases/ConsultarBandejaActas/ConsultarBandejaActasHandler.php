<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaActas;

use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;

/**
 * Manejador del caso de uso para consultar la bandeja de actas del curador,
 * aplicando filtros, orden y resolución de nombres de solicitantes.
 *
 * {@see ConsultarBandejaActasInput}
 * {@see ConsultarBandejaActasOutput}
 */
final class ConsultarBandejaActasHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly UsuarioNombrePort $usuarios,
    ) {}

    /**
     * Ejecuta el caso de uso.
     */
    public function handle(ConsultarBandejaActasInput $input): ConsultarBandejaActasOutput
    {
        $investigadorIds = match (true) {
            $input->investigadorPropio !== null => [$input->investigadorPropio],
            $input->busquedaInvestigador !== '' => $this->usuarios->buscarIdsPorNombre($input->busquedaInvestigador),
            default => null,
        };

        $filas = $this->actaRepo->listarParaBandeja(
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
            fn (array $fila): FilaActaBandeja => new FilaActaBandeja(
                actaId: $fila['actaId'],
                numeroPrestamo: $fila['numeroPrestamo'],
                numeroSolicitud: $fila['numeroSolicitud'],
                solicitanteNombre: $fila['investigadorId'] !== null
                    ? ($nombres[$fila['investigadorId']] ?? null)
                    : null,
                estado: $fila['estado'],
                fecha: $fila['fecha'],
            ),
            $filas,
        );

        return new ConsultarBandejaActasOutput(filas: $filasVista);
    }
}
