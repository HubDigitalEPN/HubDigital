<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Manejador del caso de uso para consultar un préstamo.
 * 
 * {@see ConsultarPrestamoInput}
 * {@see ConsultarPrestamoOutput}
 */
final class ConsultarPrestamoHandler
{
    /**
     * @param PrestamoRepositoryInterface $repo Repositorio de préstamos.
     * @param ActaPrestamoRepositoryInterface $actaRepo Repositorio de actas de préstamo.
     */
    public function __construct(
        private readonly PrestamoRepositoryInterface $repo,
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarPrestamoInput $input Datos de entrada.
     * @return ConsultarPrestamoOutput Datos del préstamo consultado.
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     */
    public function handle(ConsultarPrestamoInput $input): ConsultarPrestamoOutput
    {
        $id = PrestamoId::fromString($input->prestamoId);

        $prestamo = $this->repo->buscarPorId($id);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($id);
        }

        $acta = $this->actaRepo->buscarPorId(
            ActaPrestamoId::fromString((string) $prestamo->actaPrestamoId())
        );

        return new ConsultarPrestamoOutput(
            prestamoId: (string) $prestamo->id(),
            actaPrestamoId: (string) $prestamo->actaPrestamoId(),
            investigadorId: $prestamo->investigadorId(),
            estado: $prestamo->estado(),
            iniciadoEn: $prestamo->iniciadoEn(),
            fechaFin: $prestamo->fechaFin(),
            numeroPrestamo: $acta !== null ? (string) $acta->numeroPrestamo() : '—',
        );
    }
}
