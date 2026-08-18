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

    /** @var array<string, string> registroId => especimenId */
    private array $vinculos = [];

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

    /**
     * El vínculo con el inventario vive fuera del agregado, así que en memoria basta
     * con recordarlo aparte: los tests que lo usan preguntan por {@see vinculos()}.
     *
     * @param  array<string, string>  $especimenIdPorRegistroId
     */
    public function vincularEspecimenes(array $especimenIdPorRegistroId): int
    {
        $anotadas = 0;

        foreach ($especimenIdPorRegistroId as $registroId => $especimenId) {
            if (isset($this->vinculos[$registroId])) {
                continue;
            }

            $this->vinculos[$registroId] = $especimenId;
            $anotadas++;
        }

        return $anotadas;
    }

    /** @return array<string, string> registroId => especimenId */
    public function vinculos(): array
    {
        return $this->vinculos;
    }
}
