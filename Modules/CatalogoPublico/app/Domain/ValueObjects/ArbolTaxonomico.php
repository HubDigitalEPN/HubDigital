<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class ArbolTaxonomico
{
    /**
     * @param  list<NodoTaxonomico>  $nodosJerarquicos
     * @param  list<NodoEspecie>  $especies
     * @param  array<string, list<string>>  $especimenesPorEspecie  especie → [occurrenceIDs]
     */
    private function __construct(
        public array $nodosJerarquicos,
        public array $especies,
        public array $especimenesPorEspecie,
    ) {}

    /**
     * @param  list<NodoTaxonomico>  $nodosJerarquicos
     * @param  list<NodoEspecie>  $especies
     * @param  array<string, list<string>>  $especimenesPorEspecie
     */
    public static function construir(
        array $nodosJerarquicos,
        array $especies,
        array $especimenesPorEspecie,
    ): self {
        return new self(
            nodosJerarquicos: $nodosJerarquicos,
            especies: $especies,
            especimenesPorEspecie: $especimenesPorEspecie,
        );
    }

    public function tieneEspecie(string $nombreEspecie): bool
    {
        foreach ($this->especies as $especie) {
            if ($especie->especie === $nombreEspecie) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function especimenesDeEspecie(string $nombreEspecie): array
    {
        return $this->especimenesPorEspecie[$nombreEspecie] ?? [];
    }

    /** @return list<NodoTaxonomico> */
    public function nodosDeRango(RangoTaxonomico $rango): array
    {
        return array_values(
            array_filter($this->nodosJerarquicos, fn (NodoTaxonomico $n) => $n->rango === $rango)
        );
    }

    public function estaVacio(): bool
    {
        return $this->especies === [];
    }
}
