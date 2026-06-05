<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\Coordenada;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;

class Localidad
{
    private function __construct(
        private readonly LocalidadId $id,
        private string $nombreCanonico,
        private RangoLocalidad $rango,
        private ?LocalidadId $padreId,
        private ?Coordenada $coordenada,
        private ?string $geodeticDatum,
        private ?float $coordinateUncertaintyM,
        private ?string $latLonVerbatim,
        private ?string $country,
        private ?string $stateProvince,
        private ?string $municipality,
    ) {}

    public static function crear(
        LocalidadId $id,
        string $nombreCanonico,
        string $rango,
        ?string $padreId = null,
        ?float $latitud = null,
        ?float $longitud = null,
        ?string $geodeticDatum = null,
        ?float $coordinateUncertaintyM = null,
        ?string $latLonVerbatim = null,
        ?string $country = null,
        ?string $stateProvince = null,
        ?string $municipality = null,
    ): self {
        $rangoVO = RangoLocalidad::desdeValorFlexible($rango);

        if ($rangoVO === null) {
            throw new \InvalidArgumentException(
                "Rango de localidad inválido: '{$rango}'. Valores válidos: ".
                implode(', ', RangoLocalidad::valoresAceptados())
            );
        }

        $nombre = trim($nombreCanonico);
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre canónico de la localidad no puede estar vacío.');
        }

        $coordenada = self::componerCoordenada($latitud, $longitud);

        return new self(
            id: $id,
            nombreCanonico: $nombre,
            rango: $rangoVO,
            padreId: $padreId !== null ? LocalidadId::desde($padreId) : null,
            coordenada: $coordenada,
            geodeticDatum: self::normalizarOpcional($geodeticDatum),
            coordinateUncertaintyM: $coordinateUncertaintyM,
            latLonVerbatim: self::normalizarOpcional($latLonVerbatim),
            country: self::normalizarOpcional($country),
            stateProvince: self::normalizarOpcional($stateProvince),
            municipality: self::normalizarOpcional($municipality),
        );
    }

    public static function reconstituir(
        LocalidadId $id,
        string $nombreCanonico,
        RangoLocalidad $rango,
        ?LocalidadId $padreId,
        ?Coordenada $coordenada,
        ?string $geodeticDatum,
        ?float $coordinateUncertaintyM,
        ?string $latLonVerbatim,
        ?string $country,
        ?string $stateProvince,
        ?string $municipality,
    ): self {
        return new self(
            id: $id,
            nombreCanonico: $nombreCanonico,
            rango: $rango,
            padreId: $padreId,
            coordenada: $coordenada,
            geodeticDatum: $geodeticDatum,
            coordinateUncertaintyM: $coordinateUncertaintyM,
            latLonVerbatim: $latLonVerbatim,
            country: $country,
            stateProvince: $stateProvince,
            municipality: $municipality,
        );
    }

    public function actualizar(
        string $nombreCanonico,
        string $rango,
        ?string $padreId = null,
        ?float $latitud = null,
        ?float $longitud = null,
        ?string $geodeticDatum = null,
        ?float $coordinateUncertaintyM = null,
        ?string $latLonVerbatim = null,
        ?string $country = null,
        ?string $stateProvince = null,
        ?string $municipality = null,
    ): void {
        $rangoVO = RangoLocalidad::desdeValorFlexible($rango);

        if ($rangoVO === null) {
            throw new \InvalidArgumentException(
                "Rango de localidad inválido: '{$rango}'. Valores válidos: ".
                implode(', ', RangoLocalidad::valoresAceptados())
            );
        }

        $nombre = trim($nombreCanonico);
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre canónico de la localidad no puede estar vacío.');
        }

        $this->nombreCanonico = $nombre;
        $this->rango = $rangoVO;
        $this->padreId = $padreId !== null ? LocalidadId::desde($padreId) : null;
        $this->coordenada = self::componerCoordenada($latitud, $longitud);
        $this->geodeticDatum = self::normalizarOpcional($geodeticDatum);
        $this->coordinateUncertaintyM = $coordinateUncertaintyM;
        $this->latLonVerbatim = self::normalizarOpcional($latLonVerbatim);
        $this->country = self::normalizarOpcional($country);
        $this->stateProvince = self::normalizarOpcional($stateProvince);
        $this->municipality = self::normalizarOpcional($municipality);
    }

    private static function componerCoordenada(?float $latitud, ?float $longitud): ?Coordenada
    {
        if ($latitud === null && $longitud === null) {
            return null;
        }

        if ($latitud === null || $longitud === null) {
            throw new \InvalidArgumentException(
                'Para registrar una coordenada se requieren tanto latitud como longitud.'
            );
        }

        return Coordenada::desde($latitud, $longitud);
    }

    private static function normalizarOpcional(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);

        return $trim === '' ? null : $trim;
    }

    public function id(): LocalidadId
    {
        return $this->id;
    }

    public function nombreCanonico(): string
    {
        return $this->nombreCanonico;
    }

    public function rango(): RangoLocalidad
    {
        return $this->rango;
    }

    public function padreId(): ?LocalidadId
    {
        return $this->padreId;
    }

    public function coordenada(): ?Coordenada
    {
        return $this->coordenada;
    }

    public function geodeticDatum(): ?string
    {
        return $this->geodeticDatum;
    }

    public function coordinateUncertaintyM(): ?float
    {
        return $this->coordinateUncertaintyM;
    }

    public function latLonVerbatim(): ?string
    {
        return $this->latLonVerbatim;
    }

    public function country(): ?string
    {
        return $this->country;
    }

    public function stateProvince(): ?string
    {
        return $this->stateProvince;
    }

    public function municipality(): ?string
    {
        return $this->municipality;
    }
}
