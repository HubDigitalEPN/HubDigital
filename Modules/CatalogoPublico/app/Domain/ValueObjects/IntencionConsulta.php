<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class IntencionConsulta
{
    private const int LIMITE_RANKING_DEFECTO = 5;

    private function __construct(
        public bool $dentroDeDominio,
        public TipoDeConsulta $tipo,
        public EntidadesExtraidas $entidades,
        public ?RangoTaxonomico $rangoAgregacion,
        public int $limiteRanking,
    ) {}

    public static function dentroDelDominio(EntidadesExtraidas $entidades): self
    {
        return new self(
            dentroDeDominio: true,
            tipo: TipoDeConsulta::Lookup,
            entidades: $entidades,
            rangoAgregacion: null,
            limiteRanking: self::LIMITE_RANKING_DEFECTO,
        );
    }

    public static function ranking(RangoTaxonomico $rango, ?int $limite = null): self
    {
        return new self(
            dentroDeDominio: true,
            tipo: TipoDeConsulta::Ranking,
            entidades: EntidadesExtraidas::vacio(),
            rangoAgregacion: $rango,
            limiteRanking: $limite ?? self::LIMITE_RANKING_DEFECTO,
        );
    }

    public static function fueraDelDominio(): self
    {
        return new self(
            dentroDeDominio: false,
            tipo: TipoDeConsulta::Lookup,
            entidades: EntidadesExtraidas::vacio(),
            rangoAgregacion: null,
            limiteRanking: self::LIMITE_RANKING_DEFECTO,
        );
    }
}
