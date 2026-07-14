<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class ContextoLLM
{
    /**
     * @param  list<DatosEspecimenParaContexto>  $especimenes
     * @param  list<EstadisticaTaxon>  $estadisticas
     */
    private function __construct(
        public string $pregunta,
        public TipoDeConsulta $tipo,
        public array $especimenes,
        public array $estadisticas,
        public ?RangoTaxonomico $rangoAgregacion,
        public ?int $totalUnicosEnRango,
    ) {}

    /** @param list<DatosEspecimenParaContexto> $especimenes */
    public static function conEspecimenes(string $pregunta, array $especimenes): self
    {
        self::validarPregunta($pregunta);

        return new self(
            pregunta: $pregunta,
            tipo: TipoDeConsulta::Lookup,
            especimenes: array_values($especimenes),
            estadisticas: [],
            rangoAgregacion: null,
            totalUnicosEnRango: null,
        );
    }

    /** @param list<EstadisticaTaxon> $estadisticas */
    public static function conRanking(
        string $pregunta,
        RangoTaxonomico $rango,
        array $estadisticas,
        int $totalUnicosEnRango,
    ): self {
        self::validarPregunta($pregunta);

        if ($totalUnicosEnRango < 0) {
            throw new \InvalidArgumentException('El total de únicos no puede ser negativo');
        }

        return new self(
            pregunta: $pregunta,
            tipo: TipoDeConsulta::Ranking,
            especimenes: [],
            estadisticas: array_values($estadisticas),
            rangoAgregacion: $rango,
            totalUnicosEnRango: $totalUnicosEnRango,
        );
    }

    public function estaVacio(): bool
    {
        return $this->tipo === TipoDeConsulta::Ranking
            ? $this->estadisticas === []
            : $this->especimenes === [];
    }

    public function esRanking(): bool
    {
        return $this->tipo === TipoDeConsulta::Ranking;
    }

    public function incluyeOccurrenceID(string $occurrenceID): bool
    {
        foreach ($this->especimenes as $especimen) {
            if ($especimen->occurrenceID === $occurrenceID) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function occurrenceIDs(): array
    {
        return array_values(array_map(
            static fn (DatosEspecimenParaContexto $e): string => $e->occurrenceID,
            $this->especimenes,
        ));
    }

    public function especimenPor(string $occurrenceID): ?DatosEspecimenParaContexto
    {
        foreach ($this->especimenes as $especimen) {
            if ($especimen->occurrenceID === $occurrenceID) {
                return $especimen;
            }
        }

        return null;
    }

    private static function validarPregunta(string $pregunta): void
    {
        if (trim($pregunta) === '') {
            throw new \InvalidArgumentException('La pregunta no puede ser vacía en el contexto del LLM');
        }
    }
}
