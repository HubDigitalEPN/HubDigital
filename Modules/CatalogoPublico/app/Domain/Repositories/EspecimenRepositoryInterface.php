<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Repositories;

use Modules\CatalogoPublico\Domain\Entities\Especimen;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenId;

interface EspecimenRepositoryInterface
{
    public function nextIdentity(): EspecimenId;

    public function guardar(Especimen $especimen): void;

    public function buscarPorOccurrenceID(string $occurrenceID): ?Especimen;
}
