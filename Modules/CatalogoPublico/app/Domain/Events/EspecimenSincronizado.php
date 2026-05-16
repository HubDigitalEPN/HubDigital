<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Events;

use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenDivulgableId;

final class EspecimenSincronizado extends DomainEvent
{
    public function __construct(
        public readonly EspecimenDivulgableId $id,
        public readonly string $occurrenceID,
        public readonly ConfiguracionVisibilidad $configuracion,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'catalogo.especimen.sincronizado';
    }
}
