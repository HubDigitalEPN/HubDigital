<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

enum TipoDeConsulta: string
{
    case Lookup = 'lookup';
    case Ranking = 'ranking';
}
