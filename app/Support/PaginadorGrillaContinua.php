<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

final class PaginadorGrillaContinua extends LengthAwarePaginator
{
    public function __construct($items, int $total, int $porPagina, int $pagina, private int $offsetVisible, public readonly array $ventana)
    {
        parent::__construct($items,$total,$porPagina,$pagina,['path'=>self::resolveCurrentPath()]);
    }
    public function firstItem(): ?int { return $this->count() ? $this->offsetVisible+1 : null; }
    public function lastItem(): ?int { return $this->count() ? $this->offsetVisible+$this->count() : null; }
}
