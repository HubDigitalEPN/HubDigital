<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

final readonly class FiltrosPendientes
{
    public function __construct(
        public ?string $busquedaCatalogo = null,
        public ?string $busquedaTaxonomia = null,
        public ?string $fechaDesde = null,
        public ?string $fechaHasta = null,
        public ?string $colector = null,
    ) {}

    public static function vacio(): self
    {
        return new self;
    }

    public function tieneFiltros(): bool
    {
        return $this->busquedaCatalogo !== null
            || $this->busquedaTaxonomia !== null
            || $this->fechaDesde !== null
            || $this->fechaHasta !== null
            || $this->colector !== null;
    }
}
