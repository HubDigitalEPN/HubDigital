<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Events;

use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenDivulgableId;

final class ConfiguracionDivulgacionModificada extends DomainEvent
{
    public function __construct(
        public readonly EspecimenDivulgableId $id,
        public readonly string $occurrenceID,
        public readonly ConfiguracionVisibilidad $configuracionAnterior,
        public readonly ConfiguracionVisibilidad $configuracionNueva,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'catalogo.especimen.configuracion_modificada';
    }
}
