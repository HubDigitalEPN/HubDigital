<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Manejador del caso de uso para consultar el detalle completo de un préstamo,
 * recorriendo Préstamo → Acta → Solicitud vía repositorios.
 *
 * Reutilizado por las pantallas de detalle (investigador, curador) y auditoría.
 *
 * {@see ConsultarDetallePrestamoInput}
 * {@see ConsultarDetallePrestamoOutput}
 */
final class ConsultarDetallePrestamoHandler
{
    /**
     * @param PrestamoRepositoryInterface $prestamoRepo Repositorio de préstamos.
     * @param ActaPrestamoRepositoryInterface $actaRepo Repositorio de actas de préstamo.
     * @param SolicitudPrestamoRepositoryInterface $solicitudRepo Repositorio de solicitudes de préstamo.
     * @param UsuarioNombrePort $usuarioNombre Resolución del nombre legible del validador.
     */
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly UsuarioNombrePort $usuarioNombre,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarDetallePrestamoInput $input Datos de entrada.
     * @return ConsultarDetallePrestamoOutput|null Detalle del préstamo, o null si no existe.
     */
    public function handle(ConsultarDetallePrestamoInput $input): ?ConsultarDetallePrestamoOutput
    {
        $prestamo = $this->prestamoRepo->buscarPorId(PrestamoId::fromString($input->prestamoId));

        if ($prestamo === null) {
            return null;
        }

        $acta = $this->actaRepo->buscarPorId(
            ActaPrestamoId::fromString((string) $prestamo->actaPrestamoId())
        );

        $solicitud = $acta !== null
            ? $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId())
            : null;

        $validadaPor = $acta?->validadaPor();
        $nombreValidador = $validadaPor !== null
            ? ($this->usuarioNombre->obtenerNombre($validadaPor) ?? $validadaPor)
            : null;

        return new ConsultarDetallePrestamoOutput(
            prestamoId: (string) $prestamo->id(),
            investigadorId: $prestamo->investigadorId(),
            estadoPrestamo: $prestamo->estado(),
            iniciadoEn: $prestamo->iniciadoEn(),
            fechaFin: $prestamo->fechaFin(),
            actaId: $acta !== null ? (string) $acta->id() : null,
            numeroPrestamo: $acta !== null ? (string) $acta->numeroPrestamo() : '—',
            actaEstado: $acta?->estado()->value,
            tipoPrestamo: $acta?->tipoPrestamo()->value,
            alcancePrestamo: $acta?->alcancePrestamo()->value,
            fechaInicioActa: $acta?->fechaInicio(),
            fechaFinActa: $acta?->fechaFin(),
            condicionesGenerales: $acta?->condicionesGenerales(),
            motivoDevolucion: $acta?->motivoDevolucion(),
            nombreValidador: $nombreValidador,
            solicitudId: $solicitud !== null ? (string) $solicitud->id() : null,
            solicitudEstado: $solicitud?->estado()->value,
            numeroSolicitud: $solicitud !== null ? (string) $solicitud->numeroSolicitud() : null,
            tituloEstudio: $solicitud?->tituloEstudio(),
            institucionAdscripcion: $solicitud?->institucionAdscripcion(),
            lineaInvestigacion: $solicitud?->lineaInvestigacion(),
            propositoPrestamo: $solicitud?->propositoPrestamo(),
            duracionPropuestaMeses: $solicitud?->duracionPropuestaMeses(),
            comentarioCurador: $solicitud?->comentarioCurador(),
        );
    }
}
