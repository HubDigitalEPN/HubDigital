<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class InMemoryPrestamoRepository implements PrestamoRepositoryInterface
{
    /** @var array<string, Prestamo> */
    private array $store = [];

    public function guardar(Prestamo $prestamo): void
    {
        $this->store[(string) $prestamo->id()] = $prestamo;
    }

    public function buscarPorId(PrestamoId $id): ?Prestamo
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorActaId(ActaPrestamoId $actaId): ?Prestamo
    {
        foreach ($this->store as $prestamo) {
            if ((string) $prestamo->actaPrestamoId() === (string) $actaId) {
                return $prestamo;
            }
        }

        return null;
    }

    public function listarActivos(): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Prestamo $p) => $p->estado()->equals(EstadoPrestamo::Activo),
        ));
    }

    public function nextIdentity(): PrestamoId
    {
        return PrestamoId::generate();
    }
}
