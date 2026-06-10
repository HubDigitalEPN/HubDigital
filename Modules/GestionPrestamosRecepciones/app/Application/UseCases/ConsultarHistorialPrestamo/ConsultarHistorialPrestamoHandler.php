<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Manejador del caso de uso para consultar el historial de un préstamo.
 * 
 * {@see ConsultarHistorialPrestamoInput}
 * {@see ConsultarHistorialPrestamoOutput}
 */
final class ConsultarHistorialPrestamoHandler
{
    /**
     * @param PrestamoRepositoryInterface $repo Repositorio de préstamos.
     * @param HistorialPort $historial Puerto para acceder al historial.
     */
    public function __construct(
        private readonly PrestamoRepositoryInterface $repo,
        private readonly HistorialPort $historial,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarHistorialPrestamoInput $input Datos de entrada.
     * @return ConsultarHistorialPrestamoOutput Datos del historial del préstamo.
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     */
    public function handle(ConsultarHistorialPrestamoInput $input): ConsultarHistorialPrestamoOutput
    {
        $id = PrestamoId::fromString($input->prestamoId);

        $prestamo = $this->repo->buscarPorId($id);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($id);
        }

        $eventos = $this->historial->obtenerEventosDePrestamo($id);

        return ConsultarHistorialPrestamoOutput::fromEventos((string) $id, $eventos);
    }
}
