<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

use DateTimeImmutable;

final readonly class FiltrosBusqueda
{
    private function __construct(
        public readonly array $codigosCatalogo,
        public readonly array $preparaciones,
        public readonly ?string $taxonNombre,
        public readonly array $geografias,
        public readonly array $colectores,
        public readonly ?DateTimeImmutable $fechaDesde,
        public readonly ?DateTimeImmutable $fechaHasta,
        public readonly array $metodosRecoleccion,
        public readonly ?float $latMin,
        public readonly ?float $latMax,
        public readonly ?float $lonMin,
        public readonly ?float $lonMax,
        public readonly ?float $elevDesde,
        public readonly ?float $elevHasta,
        public readonly array $biomas,
        public readonly ?string $habitat,
        public readonly ?string $tipo,
        public readonly ?string $casta,
        public readonly ?string $estadio,
        public readonly ?string $provincia,
        public readonly ?string $filoId,
        public readonly ?int $mes,
        public readonly ?string $identificacion,
        public readonly bool $soloUbicacion,
        public readonly bool $datosCompletos,
        public readonly ?string $pais = null,
        public readonly ?string $disposicion = null,
        public readonly ?string $provinciaExcluida = null,
    ) {}

    public static function vacio(): self
    {
        return new self(
            codigosCatalogo: [],
            preparaciones: [],
            taxonNombre: null,
            geografias: [],
            colectores: [],
            fechaDesde: null,
            fechaHasta: null,
            metodosRecoleccion: [],
            latMin: null,
            latMax: null,
            lonMin: null,
            lonMax: null,
            elevDesde: null,
            elevHasta: null,
            biomas: [],
            habitat: null,
            tipo: null,
            casta: null,
            estadio: null,
            provincia: null,
            filoId: null,
            mes: null,
            identificacion: null,
            soloUbicacion: false,
            datosCompletos: false,
        );
    }

    public static function desde(array $datos): self
    {
        $normalizar = static fn (string $v): string => (string) preg_replace('/\s+/', ' ', trim($v));

        $normalizarArray = static fn (array $arr): array => array_values(
            array_filter(array_map(static fn ($v) => $normalizar((string) $v), $arr))
        );

        $codigosCatalogo = [];
        if (isset($datos['filtroCatalogo']) && $datos['filtroCatalogo'] !== '') {
            $codigosCatalogo = $normalizarArray(explode(',', (string) $datos['filtroCatalogo']));
        }

        $fechaDesde = null;
        $fechaHasta = null;
        if (! empty($datos['filtroFechaDesde'])) {
            try {
                $fechaDesde = new DateTimeImmutable((string) $datos['filtroFechaDesde']);
            } catch (\Throwable) {
            }
        }
        if (! empty($datos['filtroFechaHasta'])) {
            try {
                $fechaHasta = new DateTimeImmutable((string) $datos['filtroFechaHasta']);
            } catch (\Throwable) {
            }
        }

        $latMin = is_numeric($datos['filtroLatMin'] ?? '') ? (float) $datos['filtroLatMin'] : null;
        $latMax = is_numeric($datos['filtroLatMax'] ?? '') ? (float) $datos['filtroLatMax'] : null;
        $lonMin = is_numeric($datos['filtroLonMin'] ?? '') ? (float) $datos['filtroLonMin'] : null;
        $lonMax = is_numeric($datos['filtroLonMax'] ?? '') ? (float) $datos['filtroLonMax'] : null;

        $elevDesde = is_numeric($datos['filtroElevDesde'] ?? '') ? (float) $datos['filtroElevDesde'] : null;
        $elevHasta = is_numeric($datos['filtroElevHasta'] ?? '') ? (float) $datos['filtroElevHasta'] : null;

        $taxonNombre = null;
        if (! empty($datos['filtroTaxon'])) {
            $v = $normalizar((string) $datos['filtroTaxon']);
            $taxonNombre = $v !== '' ? $v : null;
        }

        $colectores = [];
        if (! empty($datos['filtroColector'])) {
            $v = $normalizar((string) $datos['filtroColector']);
            if ($v !== '') {
                $colectores = [$v];
            }
        }

        $textoOpcional = static function (string $clave) use ($datos, $normalizar): ?string {
            $texto = $normalizar((string) ($datos[$clave] ?? ''));

            return $texto === '' ? null : mb_substr($texto, 0, 120);
        };

        return new self(
            codigosCatalogo: $codigosCatalogo,
            preparaciones: $normalizarArray($datos['filtroPreparaciones'] ?? []),
            taxonNombre: $taxonNombre,
            geografias: $normalizarArray($datos['filtroGeografias'] ?? []),
            colectores: $colectores,
            fechaDesde: $fechaDesde,
            fechaHasta: $fechaHasta,
            metodosRecoleccion: $normalizarArray($datos['filtroMetodos'] ?? []),
            latMin: $latMin,
            latMax: $latMax,
            lonMin: $lonMin,
            lonMax: $lonMax,
            elevDesde: $elevDesde,
            elevHasta: $elevHasta,
            biomas: $normalizarArray($datos['filtroBiomas'] ?? []),
            habitat: $textoOpcional('filtroHabitat'),
            tipo: CondicionMaterialPublica::tipoFiltro($textoOpcional('filtroTipo')),
            disposicion: CondicionMaterialPublica::disposicionFiltro($textoOpcional('filtroDisposicion')),
            casta: $textoOpcional('filtroCasta'),
            estadio: $textoOpcional('filtroEstadio'),
            provincia: $textoOpcional('filtroProvincia'),
            provinciaExcluida: $textoOpcional('filtroProvinciaExcluida'),
            pais: $textoOpcional('filtroPais'),
            filoId: preg_match('/^[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}$/', (string) ($datos['filtroFiloId'] ?? '')) ? (string) $datos['filtroFiloId'] : null,
            mes: filter_var($datos['filtroMes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: null,
            identificacion: in_array(($datos['filtroIdentificacion'] ?? ''), ['especie', 'superior'], true) ? (string) $datos['filtroIdentificacion'] : null,
            soloUbicacion: ($datos['filtroSoloUbicacion'] ?? '') === '1',
            datosCompletos: ($datos['filtroDatosCompletos'] ?? '') === '1',
        );
    }

    public function estaVacio(): bool
    {
        return $this->codigosCatalogo === []
            && $this->preparaciones === []
            && $this->taxonNombre === null
            && $this->geografias === []
            && $this->colectores === []
            && $this->fechaDesde === null
            && $this->fechaHasta === null
            && $this->metodosRecoleccion === []
            && $this->latMin === null
            && $this->latMax === null
            && $this->lonMin === null
            && $this->lonMax === null
            && $this->elevDesde === null
            && $this->elevHasta === null
            && $this->biomas === []
            && $this->habitat === null
            && $this->tipo === null
            && $this->disposicion === null
            && $this->casta === null
            && $this->estadio === null
            && $this->provincia === null
            && $this->provinciaExcluida === null
            && $this->pais === null
            && $this->filoId === null
            && $this->mes === null
            && $this->identificacion === null
            && ! $this->soloUbicacion
            && ! $this->datosCompletos;
    }
}
