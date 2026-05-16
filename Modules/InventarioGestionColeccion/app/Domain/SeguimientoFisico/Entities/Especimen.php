<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificadorEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoIdentificadorEspecimen;

class Especimen
{
    private function __construct(
        private readonly EspecimenId $id,
        private readonly string $codigoCatalogo,
        private readonly string $taxonId,
        private string $localidad,
        private string $fechaColecta,
        private string $colector,
        private ?string $entidadDepositanteId,
        private EstadoEspecimen $estado,
        private ?string $occurrenceId,
        private ?string $catalogNumber,
        private ?string $oldCode,
        private ?string $cardexLiquidCollectionCode,
        private ?int $individualCount,
        private ?string $preparations,
        private ?string $disposition,
        private ?string $occurrenceStatus,
        private ?string $specimenNotes,
        private ?string $country,
        private ?string $stateProvince,
        private ?string $municipality,
        private ?string $localityName,
        private ?float $decimalLatitude,
        private ?float $decimalLongitude,
        private ?string $geodeticDatum,
        private ?float $elevationInMeters,
        private ?string $biome,
        private ?string $habitat,
        /** @var IdentificadorEspecimen[] */
        private array $identificadores,
    ) {}

    public static function crear(
        EspecimenId $id,
        string $codigoCatalogo,
        string $taxonId,
        string $localidad,
        string $fechaColecta,
        string $colector,
        ?string $entidadDepositanteId = null,
        ?string $occurrenceId = null,
        ?string $catalogNumber = null,
        ?string $oldCode = null,
        ?string $cardexLiquidCollectionCode = null,
        ?int $individualCount = null,
        ?string $preparations = null,
        ?string $disposition = null,
        ?string $occurrenceStatus = null,
        ?string $specimenNotes = null,
        ?string $country = null,
        ?string $stateProvince = null,
        ?string $municipality = null,
        ?string $localityName = null,
        ?float $decimalLatitude = null,
        ?float $decimalLongitude = null,
        ?string $geodeticDatum = null,
        ?float $elevationInMeters = null,
        ?string $biome = null,
        ?string $habitat = null,
        array $identificadores = [],
    ): self {
        $localidad = trim($localidad);
        $localityName = self::limpiarTexto($localityName) ?? $localidad;

        return new self(
            id: $id,
            codigoCatalogo: trim($codigoCatalogo),
            taxonId: $taxonId,
            localidad: $localidad,
            fechaColecta: $fechaColecta,
            colector: trim($colector),
            entidadDepositanteId: $entidadDepositanteId,
            estado: EstadoEspecimen::Disponible,
            occurrenceId: self::limpiarTexto($occurrenceId),
            catalogNumber: self::limpiarTexto($catalogNumber),
            oldCode: self::limpiarTexto($oldCode),
            cardexLiquidCollectionCode: self::limpiarTexto($cardexLiquidCollectionCode),
            individualCount: $individualCount,
            preparations: self::limpiarTexto($preparations),
            disposition: self::limpiarTexto($disposition),
            occurrenceStatus: self::limpiarTexto($occurrenceStatus),
            specimenNotes: self::limpiarTexto($specimenNotes),
            country: self::limpiarTexto($country),
            stateProvince: self::limpiarTexto($stateProvince),
            municipality: self::limpiarTexto($municipality),
            localityName: $localityName,
            decimalLatitude: $decimalLatitude,
            decimalLongitude: $decimalLongitude,
            geodeticDatum: self::limpiarTexto($geodeticDatum),
            elevationInMeters: $elevationInMeters,
            biome: self::limpiarTexto($biome),
            habitat: self::limpiarTexto($habitat),
            identificadores: self::normalizarIdentificadores(
                identificadores: $identificadores,
                codigoCatalogo: trim($codigoCatalogo),
                occurrenceId: self::limpiarTexto($occurrenceId),
                catalogNumber: self::limpiarTexto($catalogNumber),
                oldCode: self::limpiarTexto($oldCode),
                cardexLiquidCollectionCode: self::limpiarTexto($cardexLiquidCollectionCode),
            ),
        );
    }

    public static function reconstituir(
        EspecimenId $id,
        string $codigoCatalogo,
        string $taxonId,
        string $localidad,
        string $fechaColecta,
        string $colector,
        EstadoEspecimen $estado,
        ?string $entidadDepositanteId = null,
        ?string $occurrenceId = null,
        ?string $catalogNumber = null,
        ?string $oldCode = null,
        ?string $cardexLiquidCollectionCode = null,
        ?int $individualCount = null,
        ?string $preparations = null,
        ?string $disposition = null,
        ?string $occurrenceStatus = null,
        ?string $specimenNotes = null,
        ?string $country = null,
        ?string $stateProvince = null,
        ?string $municipality = null,
        ?string $localityName = null,
        ?float $decimalLatitude = null,
        ?float $decimalLongitude = null,
        ?string $geodeticDatum = null,
        ?float $elevationInMeters = null,
        ?string $biome = null,
        ?string $habitat = null,
        array $identificadores = [],
    ): self {
        return new self(
            id: $id,
            codigoCatalogo: $codigoCatalogo,
            taxonId: $taxonId,
            localidad: $localidad,
            fechaColecta: $fechaColecta,
            colector: $colector,
            entidadDepositanteId: $entidadDepositanteId,
            estado: $estado,
            occurrenceId: $occurrenceId,
            catalogNumber: $catalogNumber,
            oldCode: $oldCode,
            cardexLiquidCollectionCode: $cardexLiquidCollectionCode,
            individualCount: $individualCount,
            preparations: $preparations,
            disposition: $disposition,
            occurrenceStatus: $occurrenceStatus,
            specimenNotes: $specimenNotes,
            country: $country,
            stateProvince: $stateProvince,
            municipality: $municipality,
            localityName: $localityName ?? $localidad,
            decimalLatitude: $decimalLatitude,
            decimalLongitude: $decimalLongitude,
            geodeticDatum: $geodeticDatum,
            elevationInMeters: $elevationInMeters,
            biome: $biome,
            habitat: $habitat,
            identificadores: $identificadores,
        );
    }

    public function actualizar(
        string $localidad,
        string $fechaColecta,
        string $colector,
        ?string $entidadDepositanteId,
        ?string $country = null,
        ?string $stateProvince = null,
        ?string $municipality = null,
        ?string $localityName = null,
        ?float $decimalLatitude = null,
        ?float $decimalLongitude = null,
        ?string $geodeticDatum = null,
        ?float $elevationInMeters = null,
        ?string $biome = null,
        ?string $habitat = null,
        ?string $preparations = null,
        ?string $disposition = null,
        ?string $occurrenceStatus = null,
        ?string $specimenNotes = null,
    ): void {
        $this->localidad = trim($localidad);
        $this->fechaColecta = $fechaColecta;
        $this->colector = trim($colector);
        $this->entidadDepositanteId = $entidadDepositanteId;
        $this->country = self::limpiarTexto($country) ?? $this->country;
        $this->stateProvince = self::limpiarTexto($stateProvince) ?? $this->stateProvince;
        $this->municipality = self::limpiarTexto($municipality) ?? $this->municipality;
        $this->localityName = self::limpiarTexto($localityName) ?? $this->localityName ?? $this->localidad;
        $this->decimalLatitude = $decimalLatitude ?? $this->decimalLatitude;
        $this->decimalLongitude = $decimalLongitude ?? $this->decimalLongitude;
        $this->geodeticDatum = self::limpiarTexto($geodeticDatum) ?? $this->geodeticDatum;
        $this->elevationInMeters = $elevationInMeters ?? $this->elevationInMeters;
        $this->biome = self::limpiarTexto($biome) ?? $this->biome;
        $this->habitat = self::limpiarTexto($habitat) ?? $this->habitat;
        $this->preparations = self::limpiarTexto($preparations) ?? $this->preparations;
        $this->disposition = self::limpiarTexto($disposition) ?? $this->disposition;
        $this->occurrenceStatus = self::limpiarTexto($occurrenceStatus) ?? $this->occurrenceStatus;
        $this->specimenNotes = self::limpiarTexto($specimenNotes) ?? $this->specimenNotes;
    }

    public function marcarEnPrestamo(): void
    {
        $this->estado = EstadoEspecimen::EnPrestamo;
    }

    public function marcarDisponible(): void
    {
        $this->estado = EstadoEspecimen::Disponible;
    }

    public function id(): EspecimenId
    {
        return $this->id;
    }

    public function codigoCatalogo(): string
    {
        return $this->codigoCatalogo;
    }

    public function taxonId(): string
    {
        return $this->taxonId;
    }

    public function localidad(): string
    {
        return $this->localidad;
    }

    public function fechaColecta(): string
    {
        return $this->fechaColecta;
    }

    public function colector(): string
    {
        return $this->colector;
    }

    public function entidadDepositanteId(): ?string
    {
        return $this->entidadDepositanteId;
    }

    public function occurrenceId(): ?string
    {
        return $this->occurrenceId;
    }

    public function catalogNumber(): ?string
    {
        return $this->catalogNumber;
    }

    public function oldCode(): ?string
    {
        return $this->oldCode;
    }

    public function cardexLiquidCollectionCode(): ?string
    {
        return $this->cardexLiquidCollectionCode;
    }

    public function individualCount(): ?int
    {
        return $this->individualCount;
    }

    public function preparations(): ?string
    {
        return $this->preparations;
    }

    public function disposition(): ?string
    {
        return $this->disposition;
    }

    public function occurrenceStatus(): ?string
    {
        return $this->occurrenceStatus;
    }

    public function specimenNotes(): ?string
    {
        return $this->specimenNotes;
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

    public function localityName(): ?string
    {
        return $this->localityName;
    }

    public function decimalLatitude(): ?float
    {
        return $this->decimalLatitude;
    }

    public function decimalLongitude(): ?float
    {
        return $this->decimalLongitude;
    }

    public function geodeticDatum(): ?string
    {
        return $this->geodeticDatum;
    }

    public function elevationInMeters(): ?float
    {
        return $this->elevationInMeters;
    }

    public function biome(): ?string
    {
        return $this->biome;
    }

    public function habitat(): ?string
    {
        return $this->habitat;
    }

    /** @return IdentificadorEspecimen[] */
    public function identificadores(): array
    {
        return $this->identificadores;
    }

    public function estado(): EstadoEspecimen
    {
        return $this->estado;
    }

    private static function limpiarTexto(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        return $valor !== '' ? $valor : null;
    }

    /**
     * @param  array<int, IdentificadorEspecimen|array{tipo: string, valor: string}>  $identificadores
     * @return IdentificadorEspecimen[]
     */
    private static function normalizarIdentificadores(
        array $identificadores,
        string $codigoCatalogo,
        ?string $occurrenceId,
        ?string $catalogNumber,
        ?string $oldCode,
        ?string $cardexLiquidCollectionCode,
    ): array {
        $normalizados = [
            IdentificadorEspecimen::crear(TipoIdentificadorEspecimen::CodigoCatalogo->value, $codigoCatalogo),
        ];

        foreach ([
            TipoIdentificadorEspecimen::OccurrenceId->value => $occurrenceId,
            TipoIdentificadorEspecimen::CatalogNumber->value => $catalogNumber,
            TipoIdentificadorEspecimen::OldCode->value => $oldCode,
            TipoIdentificadorEspecimen::CardexLiquidCollectionCode->value => $cardexLiquidCollectionCode,
        ] as $tipo => $valor) {
            if ($valor !== null) {
                $normalizados[] = IdentificadorEspecimen::crear($tipo, $valor);
            }
        }

        foreach ($identificadores as $identificador) {
            if ($identificador instanceof IdentificadorEspecimen) {
                $normalizados[] = $identificador;

                continue;
            }

            $normalizados[] = IdentificadorEspecimen::crear($identificador['tipo'], $identificador['valor']);
        }

        $unicos = [];

        foreach ($normalizados as $identificador) {
            $key = $identificador->tipo()->value.'|'.$identificador->valor();
            $unicos[$key] = $identificador;
        }

        return array_values($unicos);
    }
}
