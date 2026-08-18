<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Lo que la matriz Darwin Core declara y hasta ahora se perdía por el camino.
 *
 * Agrupa tres cosas que no tenían dónde ir:
 *
 *  1. **La jerarquía taxonómica declarada** (reino..rango). Se guarda tal como llegó,
 *     resuelva o no el taxón canónico: cuando el material entra sin catalogar,
 *     `taxon_id` queda en null y esta era la única información que sobrevivía... salvo
 *     que no sobrevivía, porque nadie la escribía.
 *  2. **Columnas de la plantilla sin destino**: protocolo de muestreo, otros números de
 *     catálogo, hora del evento, proyecto, notas de colecta, medio, permiso de
 *     movilización e idioma.
 *  3. **`extra`**: todo campo normalizado que el mapeo no consumió. Es la red que hace
 *     cierta la promesa de "cero pérdida": lo que no se entiende se conserva en crudo
 *     en vez de desaparecer.
 *
 * Existe como grupo y no como veintiún parámetros sueltos porque la entidad Especimen ya
 * ronda los ochenta, y esa lista larguísima es justamente lo que hizo que el ingreso por
 * depósito se dejara treinta y dos campos sin pasar sin que nadie lo notara.
 */
final readonly class DarwinCoreExtendido
{
    /**
     * @param  array<string, mixed>  $extra  Campos que el mapeo no supo colocar.
     */
    private function __construct(
        public ?string $kingdom,
        public ?string $phylum,
        public ?string $dwcClass,
        public ?string $dwcOrder,
        public ?string $suborder,
        public ?string $family,
        public ?string $subfamily,
        public ?string $tribe,
        public ?string $genus,
        public ?string $specificEpithet,
        public ?string $infraspecificEpithet,
        public ?string $taxonRank,
        public ?string $samplingProtocol,
        public ?string $otherCatalogNumbers,
        public ?string $eventTime,
        public ?string $projectName,
        public ?string $collectionNotes,
        public ?string $medium,
        public ?string $movilizationPermit,
        public ?string $language,
        public array $extra,
    ) {}

    /**
     * Construye desde el mapeo, aceptando los nombres tal como los produce el mapper.
     *
     * @param  array<string, mixed>  $campos
     * @param  array<string, mixed>  $extra
     */
    public static function desdeCampos(array $campos, array $extra = []): self
    {
        $texto = static fn (string $clave): ?string => self::limpiar($campos[$clave] ?? null);

        return new self(
            kingdom: $texto('kingdom'),
            phylum: $texto('phylum'),
            dwcClass: $texto('dwcClass'),
            dwcOrder: $texto('dwcOrder'),
            suborder: $texto('suborder'),
            family: $texto('family'),
            subfamily: $texto('subfamily'),
            tribe: $texto('tribe'),
            genus: $texto('genus'),
            specificEpithet: $texto('specificEpithet'),
            infraspecificEpithet: $texto('infraspecificEpithet'),
            taxonRank: $texto('taxonRank'),
            samplingProtocol: $texto('samplingProtocol'),
            otherCatalogNumbers: $texto('otherCatalogNumbers'),
            eventTime: $texto('eventTime'),
            projectName: $texto('projectName'),
            collectionNotes: $texto('collectionNotes'),
            medium: $texto('medium'),
            movilizationPermit: $texto('movilizationPermit'),
            language: $texto('language'),
            extra: array_filter($extra, static fn ($v): bool => $v !== null && $v !== ''),
        );
    }

    public static function vacio(): self
    {
        return self::desdeCampos([]);
    }

    /** ¿Trae algo la jerarquía declarada por el depositante? */
    public function tieneJerarquia(): bool
    {
        return $this->kingdom !== null
            || $this->phylum !== null
            || $this->dwcClass !== null
            || $this->dwcOrder !== null
            || $this->family !== null
            || $this->genus !== null;
    }

    /**
     * Jerarquía en el formato snake_case que espera el resolutor taxonómico.
     *
     * @return array<string, string|null>
     */
    public function jerarquiaParaResolucion(): array
    {
        return [
            'kingdom' => $this->kingdom,
            'phylum' => $this->phylum,
            'class' => $this->dwcClass,
            'order' => $this->dwcOrder,
            'suborder' => $this->suborder,
            'family' => $this->family,
            'subfamily' => $this->subfamily,
            'tribe' => $this->tribe,
            'genus' => $this->genus,
            'specific_epithet' => $this->specificEpithet,
            'taxon_rank' => $this->taxonRank,
        ];
    }

    /**
     * Columnas de persistencia. El repositorio las vuelca tal cual, de modo que añadir un
     * campo aquí no obliga a tocar dos sitios más.
     *
     * @return array<string, mixed>
     */
    public function paraPersistencia(): array
    {
        return [
            'kingdom' => $this->kingdom,
            'phylum' => $this->phylum,
            'dwc_class' => $this->dwcClass,
            'dwc_order' => $this->dwcOrder,
            'suborder' => $this->suborder,
            'family' => $this->family,
            'subfamily' => $this->subfamily,
            'tribe' => $this->tribe,
            'genus' => $this->genus,
            'specific_epithet' => $this->specificEpithet,
            'infraspecific_epithet' => $this->infraspecificEpithet,
            'taxon_rank' => $this->taxonRank,
            'sampling_protocol' => $this->samplingProtocol,
            'other_catalog_numbers' => $this->otherCatalogNumbers,
            'event_time' => $this->eventTime,
            'project_name' => $this->projectName,
            'collection_notes' => $this->collectionNotes,
            'medium' => $this->medium,
            'movilization_permit' => $this->movilizationPermit,
            'language' => $this->language,
            'dwc_extra' => $this->extra,
        ];
    }

    /**
     * Reconstruye desde la fila persistida.
     *
     * @param  array<string, mixed>  $fila  Claves en snake_case, como en la tabla.
     */
    public static function desdePersistencia(array $fila): self
    {
        return self::desdeCampos(
            campos: [
                'kingdom' => $fila['kingdom'] ?? null,
                'phylum' => $fila['phylum'] ?? null,
                'dwcClass' => $fila['dwc_class'] ?? null,
                'dwcOrder' => $fila['dwc_order'] ?? null,
                'suborder' => $fila['suborder'] ?? null,
                'family' => $fila['family'] ?? null,
                'subfamily' => $fila['subfamily'] ?? null,
                'tribe' => $fila['tribe'] ?? null,
                'genus' => $fila['genus'] ?? null,
                'specificEpithet' => $fila['specific_epithet'] ?? null,
                'infraspecificEpithet' => $fila['infraspecific_epithet'] ?? null,
                'taxonRank' => $fila['taxon_rank'] ?? null,
                'samplingProtocol' => $fila['sampling_protocol'] ?? null,
                'otherCatalogNumbers' => $fila['other_catalog_numbers'] ?? null,
                'eventTime' => $fila['event_time'] ?? null,
                'projectName' => $fila['project_name'] ?? null,
                'collectionNotes' => $fila['collection_notes'] ?? null,
                'medium' => $fila['medium'] ?? null,
                'movilizationPermit' => $fila['movilization_permit'] ?? null,
                'language' => $fila['language'] ?? null,
            ],
            extra: is_array($fila['dwc_extra'] ?? null) ? $fila['dwc_extra'] : [],
        );
    }

    private static function limpiar(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
