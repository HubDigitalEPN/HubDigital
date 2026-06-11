<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Manejador del caso de uso para consultar un acta de préstamo.
 * 
 * {@see ConsultarActaPrestamoInput}
 * {@see ConsultarActaPrestamoOutput}
 */
final class ConsultarActaPrestamoHandler
{
    /**
     * @param ActaPrestamoRepositoryInterface $repo Repositorio de actas de préstamo.
     */
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $repo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarActaPrestamoInput $input Datos de entrada.
     * @return ConsultarActaPrestamoOutput Datos del acta de préstamo.
     * @throws ActaPrestamoNoEncontradaException Si el acta no existe.
     */
    public function handle(ConsultarActaPrestamoInput $input): ConsultarActaPrestamoOutput
    {
        $id = ActaPrestamoId::fromString($input->actaId);

        $acta = $this->repo->buscarPorId($id);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($id);
        }

        return ConsultarActaPrestamoOutput::fromEntity($acta);
    }
}
