<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class ConfiguracionVisibilidad
{
    private const array CAMPOS = [
        'occurrenceIDVisible',
        'scientificNameVisible',
        'individualCountVisible',
        'typeStatusVisible',
        'typeNotesVisible',
        'specimenNotesVisible',
        'samplingProtocolVisible',
        'recordedByVisible',
        'occurrenceStatusVisible',
        'familyVisible',
        'genusVisible',
        'countryVisible',
        'localityNameVisible',
        'decimalLatitudeVisible',
        'decimalLongitudeVisible',
    ];

    private function __construct(
        public bool $occurrenceIDVisible,
        public bool $scientificNameVisible,
        public bool $individualCountVisible,
        public bool $typeStatusVisible,
        public bool $typeNotesVisible,
        public bool $specimenNotesVisible,
        public bool $samplingProtocolVisible,
        public bool $recordedByVisible,
        public bool $occurrenceStatusVisible,
        public bool $familyVisible,
        public bool $genusVisible,
        public bool $countryVisible,
        public bool $localityNameVisible,
        public bool $decimalLatitudeVisible,
        public bool $decimalLongitudeVisible,
    ) {}

    public static function todosHabilitados(): self
    {
        return new self(
            occurrenceIDVisible: true,
            scientificNameVisible: true,
            individualCountVisible: true,
            typeStatusVisible: true,
            typeNotesVisible: true,
            specimenNotesVisible: true,
            samplingProtocolVisible: true,
            recordedByVisible: true,
            occurrenceStatusVisible: true,
            familyVisible: true,
            genusVisible: true,
            countryVisible: true,
            localityNameVisible: true,
            decimalLatitudeVisible: true,
            decimalLongitudeVisible: true,
        );
    }

    /**
     * @param  array<string, bool>  $flags  Claves con sufijo 'Visible', ej: ['scientificNameVisible' => true]
     */
    public static function desde(array $flags): self
    {
        $desconocidos = array_diff(array_keys($flags), self::CAMPOS);
        if ($desconocidos !== []) {
            throw new \InvalidArgumentException(
                'Flags de visibilidad desconocidos: '.implode(', ', $desconocidos)
            );
        }

        $faltantes = array_diff(self::CAMPOS, array_keys($flags));
        if ($faltantes !== []) {
            throw new \InvalidArgumentException(
                'Faltan flags de visibilidad: '.implode(', ', $faltantes)
            );
        }

        return new self(
            occurrenceIDVisible: $flags['occurrenceIDVisible'],
            scientificNameVisible: $flags['scientificNameVisible'],
            individualCountVisible: $flags['individualCountVisible'],
            typeStatusVisible: $flags['typeStatusVisible'],
            typeNotesVisible: $flags['typeNotesVisible'],
            specimenNotesVisible: $flags['specimenNotesVisible'],
            samplingProtocolVisible: $flags['samplingProtocolVisible'],
            recordedByVisible: $flags['recordedByVisible'],
            occurrenceStatusVisible: $flags['occurrenceStatusVisible'],
            familyVisible: $flags['familyVisible'],
            genusVisible: $flags['genusVisible'],
            countryVisible: $flags['countryVisible'],
            localityNameVisible: $flags['localityNameVisible'],
            decimalLatitudeVisible: $flags['decimalLatitudeVisible'],
            decimalLongitudeVisible: $flags['decimalLongitudeVisible'],
        );
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'occurrenceIDVisible' => $this->occurrenceIDVisible,
            'scientificNameVisible' => $this->scientificNameVisible,
            'individualCountVisible' => $this->individualCountVisible,
            'typeStatusVisible' => $this->typeStatusVisible,
            'typeNotesVisible' => $this->typeNotesVisible,
            'specimenNotesVisible' => $this->specimenNotesVisible,
            'samplingProtocolVisible' => $this->samplingProtocolVisible,
            'recordedByVisible' => $this->recordedByVisible,
            'occurrenceStatusVisible' => $this->occurrenceStatusVisible,
            'familyVisible' => $this->familyVisible,
            'genusVisible' => $this->genusVisible,
            'countryVisible' => $this->countryVisible,
            'localityNameVisible' => $this->localityNameVisible,
            'decimalLatitudeVisible' => $this->decimalLatitudeVisible,
            'decimalLongitudeVisible' => $this->decimalLongitudeVisible,
        ];
    }

    public function occurrenceIDVisible(): bool
    {
        return $this->occurrenceIDVisible;
    }

    public function scientificNameVisible(): bool
    {
        return $this->scientificNameVisible;
    }

    public function individualCountVisible(): bool
    {
        return $this->individualCountVisible;
    }

    public function typeStatusVisible(): bool
    {
        return $this->typeStatusVisible;
    }

    public function typeNotesVisible(): bool
    {
        return $this->typeNotesVisible;
    }

    public function specimenNotesVisible(): bool
    {
        return $this->specimenNotesVisible;
    }

    public function samplingProtocolVisible(): bool
    {
        return $this->samplingProtocolVisible;
    }

    public function recordedByVisible(): bool
    {
        return $this->recordedByVisible;
    }

    public function occurrenceStatusVisible(): bool
    {
        return $this->occurrenceStatusVisible;
    }

    public function familyVisible(): bool
    {
        return $this->familyVisible;
    }

    public function genusVisible(): bool
    {
        return $this->genusVisible;
    }

    public function countryVisible(): bool
    {
        return $this->countryVisible;
    }

    public function localityNameVisible(): bool
    {
        return $this->localityNameVisible;
    }

    public function decimalLatitudeVisible(): bool
    {
        return $this->decimalLatitudeVisible;
    }

    public function decimalLongitudeVisible(): bool
    {
        return $this->decimalLongitudeVisible;
    }
}
