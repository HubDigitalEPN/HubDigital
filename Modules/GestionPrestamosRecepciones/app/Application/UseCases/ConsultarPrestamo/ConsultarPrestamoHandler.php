<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class ConsultarPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $repo,
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
    ) {}

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
