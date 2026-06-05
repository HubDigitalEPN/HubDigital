<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

use Modules\CatalogoPublico\Domain\Exceptions\JerarquiaTaxonomicaInvalidaException;

final readonly class JerarquiaTaxonomica
{
    private function __construct(
        public string $phylum,
        public string $class,
        public string $order,
        public string $family,
        public string $genus,
        public string $specificEpithet,
        public string $scientificName,
    ) {}

    public static function desde(
        string $phylum,
        string $class,
        string $order,
        string $family,
        string $genus,
        string $specificEpithet,
        string $scientificName,
    ): self {
        foreach (['phylum' => $phylum, 'class' => $class, 'order' => $order, 'family' => $family, 'genus' => $genus, 'specificEpithet' => $specificEpithet, 'scientificName' => $scientificName] as $campo => $valor) {
            if (trim($valor) === '') {
                throw new JerarquiaTaxonomicaInvalidaException("El campo '{$campo}' no puede ser vacío en la jerarquía taxonómica");
            }
        }

        $nombreEsperado = trim($genus).' '.trim($specificEpithet);
        if ($scientificName !== $nombreEsperado) {
            throw new JerarquiaTaxonomicaInvalidaException(
                "scientificName '{$scientificName}' no coincide con genus+specificEpithet '{$nombreEsperado}'"
            );
        }

        return new self(
            phylum: $phylum,
            class: $class,
            order: $order,
            family: $family,
            genus: $genus,
            specificEpithet: $specificEpithet,
            scientificName: $scientificName,
        );
    }

    public function nombreEspecie(): string
    {
        return $this->scientificName;
    }

    public function padreDeEspecie(): string
    {
        return $this->genus;
    }

    public function valorEnRango(RangoTaxonomico $rango): string
    {
        return match ($rango) {
            RangoTaxonomico::Phylum => $this->phylum,
            RangoTaxonomico::Class_ => $this->class,
            RangoTaxonomico::Order => $this->order,
            RangoTaxonomico::Family => $this->family,
            RangoTaxonomico::Genus => $this->genus,
            RangoTaxonomico::Species => throw new \LogicException(
                'El rango Species es calculado (genus + specificEpithet) y no se puede extraer directamente de JerarquiaTaxonomica'
            ),
        };
    }
}
