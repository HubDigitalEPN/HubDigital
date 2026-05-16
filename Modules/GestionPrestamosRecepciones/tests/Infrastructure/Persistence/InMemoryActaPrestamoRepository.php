<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class InMemoryActaPrestamoRepository implements ActaPrestamoRepositoryInterface
{
    /** @var array<string, ActaPrestamo> */
    private array $store = [];

    public function guardar(ActaPrestamo $acta): void
    {
        $this->store[(string) $acta->id()] = $acta;
    }

    public function buscarPorId(ActaPrestamoId $id): ?ActaPrestamo
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorSolicitudId(SolicitudPrestamoId $solicitudId): ?ActaPrestamo
    {
        foreach ($this->store as $acta) {
            if ((string) $acta->solicitudPrestamoId() === (string) $solicitudId) {
                return $acta;
            }
        }

        return null;
    }

    public function nextIdentity(): ActaPrestamoId
    {
        return ActaPrestamoId::generate();
    }
}
