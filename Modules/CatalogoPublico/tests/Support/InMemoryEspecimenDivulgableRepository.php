<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenDivulgableId;

/**
 * Repositorio volátil para Behat. Reemplaza al Eloquent que resolvía
 * occurrenceID → especimen_id mediante un JOIN con taxonomia.especimenes;
 * aquí ese mapeo se registra explícitamente en los antecedentes.
 */
final class InMemoryEspecimenDivulgableRepository implements EspecimenDivulgableRepositoryInterface
{
    /** @var array<string, EspecimenDivulgable> indexado por especimenId */
    private array $porEspecimenId = [];

    /** @var array<string, string> occurrenceID => especimenId */
    private array $especimenIdPorOccurrence = [];

    public function registrarOccurrence(string $occurrenceID, string $especimenId): void
    {
        $this->especimenIdPorOccurrence[$occurrenceID] = $especimenId;
    }

    public function nextIdentity(): EspecimenDivulgableId
    {
        return EspecimenDivulgableId::generate();
    }

    public function guardar(EspecimenDivulgable $divulgable): void
    {
        $this->porEspecimenId[$divulgable->especimenId()] = $this->copiar($divulgable);
    }

    public function buscarPorOccurrenceID(string $occurrenceID): ?EspecimenDivulgable
    {
        $especimenId = $this->especimenIdPorOccurrence[$occurrenceID] ?? null;

        if ($especimenId === null) {
            return null;
        }

        $almacenado = $this->porEspecimenId[$especimenId] ?? null;

        return $almacenado === null ? null : $this->copiar($almacenado);
    }

    /**
     * @param  list<string>  $occurrenceIDs
     * @return list<EspecimenDivulgable>
     */
    public function buscarPorOccurrenceIDs(array $occurrenceIDs): array
    {
        $result = [];

        foreach ($occurrenceIDs as $occurrenceID) {
            $divulgable = $this->buscarPorOccurrenceID($occurrenceID);

            if ($divulgable !== null) {
                $result[] = $divulgable;
            }
        }

        return $result;
    }

    /**
     * Reconstituye una entidad fresca para no compartir la identidad mutable
     * con el llamador (espejo del comportamiento del repositorio Eloquent).
     */
    private function copiar(EspecimenDivulgable $divulgable): EspecimenDivulgable
    {
        return EspecimenDivulgable::reconstituir(
            id: $divulgable->id(),
            especimenId: $divulgable->especimenId(),
            configuracion: $divulgable->configuracion(),
        );
    }
}
