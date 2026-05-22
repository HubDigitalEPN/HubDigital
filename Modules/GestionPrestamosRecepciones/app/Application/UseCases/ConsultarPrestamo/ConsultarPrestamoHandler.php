<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class ConsultarPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $repo,
    ) {}

    public function handle(ConsultarPrestamoInput $input): ConsultarPrestamoOutput
    {
        $id = PrestamoId::fromString($input->prestamoId);

        $prestamo = $this->repo->buscarPorId($id);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($id);
        }

        return ConsultarPrestamoOutput::fromEntity($prestamo);
    }
}
