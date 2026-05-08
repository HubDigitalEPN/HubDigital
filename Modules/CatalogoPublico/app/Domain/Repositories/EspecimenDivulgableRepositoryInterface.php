<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Repositories;

use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenDivulgableId;

interface EspecimenDivulgableRepositoryInterface
{
    public function nextIdentity(): EspecimenDivulgableId;

    public function guardar(EspecimenDivulgable $divulgable): void;

    public function buscarPorOccurrenceID(string $occurrenceID): ?EspecimenDivulgable;
}
