<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class ConsultarHistorialPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $repo,
        private readonly HistorialPort $historial,
    ) {}

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
