<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

final class InMemoryMatrizEspeciesRepository implements MatrizEspeciesRepositoryInterface
{
    /** @var array<string, MatrizEspecies> */
    private array $store = [];

    public function nextIdentity(): MatrizEspeciesId
    {
        return MatrizEspeciesId::generate();
    }

    public function guardar(MatrizEspecies $matriz): void
    {
        $this->store[(string) $matriz->id()] = $matriz;
    }

    public function buscarPorId(MatrizEspeciesId $matrizId): ?MatrizEspecies
    {
        return $this->store[(string) $matrizId] ?? null;
    }

    public function buscarPorSolicitudId(string $solicitudId): ?MatrizEspecies
    {
        foreach ($this->store as $matriz) {
            if ($matriz->solicitudId() === $solicitudId) {
                return $matriz;
            }
        }

        return null;
    }
}
