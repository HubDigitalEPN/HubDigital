<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

final class ConsultarActaPrestamoHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $repo,
    ) {}

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
