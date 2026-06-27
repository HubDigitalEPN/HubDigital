<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Events;

use Modules\CatalogoPublico\Domain\ValueObjects\AutorImagen;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;

final class ImagenSubida extends DomainEvent
{
    public function __construct(
        public readonly ImagenTaxonomicaId $id,
        public readonly string $occurrenceID,
        public readonly AutorImagen $autor,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'catalogo.imagen.subida';
    }
}
