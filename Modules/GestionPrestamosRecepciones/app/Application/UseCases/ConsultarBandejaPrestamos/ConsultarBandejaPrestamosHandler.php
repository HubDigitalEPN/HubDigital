<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaPrestamos;

use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;

/**
 * Manejador del caso de uso para consultar una bandeja de préstamos, aplicando
 * filtros, orden y resolución de nombres de solicitantes.
 *
 * {@see ConsultarBandejaPrestamosInput}
 * {@see ConsultarBandejaPrestamosOutput}
 */
final class ConsultarBandejaPrestamosHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly UsuarioNombrePort $usuarios,
    ) {}

    /**
     * Ejecuta el caso de uso.
     */
    public function handle(ConsultarBandejaPrestamosInput $input): ConsultarBandejaPrestamosOutput
    {
        $investigadorIds = $input->busquedaInvestigador !== ''
            ? $this->usuarios->buscarIdsPorNombre($input->busquedaInvestigador)
            : null;

        $filas = $this->prestamoRepo->listarParaBandeja(
            investigadorId: $input->investigadorPropio,
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
            fn (array $fila): FilaPrestamoBandeja => new FilaPrestamoBandeja(
                prestamoId: $fila['prestamoId'],
                numeroPrestamo: $fila['numeroPrestamo'],
                investigadorId: $fila['investigadorId'],
                solicitanteNombre: $fila['investigadorId'] !== null
                    ? ($nombres[$fila['investigadorId']] ?? null)
                    : null,
                estado: $fila['estado'],
                iniciadoEn: $fila['iniciadoEn'],
                fechaFin: $fila['fechaFin'],
            ),
            $filas,
        );

        return new ConsultarBandejaPrestamosOutput(filas: $filasVista);
    }
}
