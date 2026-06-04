<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificadorEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoIdentificadorEspecimen;

/**
 * Especimen — entidad agregada principal del catálogo.
 *
 * El espécimen acumula campos verbatim (datos crudos del Excel) y campos
 * normalizados (FKs a taxones, localidades, muestras). Su `estado_revision`
 * indica si la información fue confirmada por el curador.
 *
 * Campos que siguen siendo required (legacy): `codigoCatalogo`, `localidad`,
 * `fechaColecta`, `colector`. La relajación final a nullable se hará cuando
 * el importador (P6) los necesite null. Solo `taxonId` es nullable en P3.
 */
class Especimen
{
    /**
     * @param  IdentificadorEspecimen[]  $identificadores
     */
    private function __construct(
        private readonly EspecimenId $id,
        private readonly string $codigoCatalogo,
        private ?string $taxonId,
        private ?string $taxonVerbatim,
        private ?string $muestraId,
        private string $localidad,
        private ?string $localidadId,
        private ?string $localidadVerbatim,
        private string $fechaColecta,
        private ?string $fechaVerbatim,
        private ?string $fechaColectaFin,
        private string $colector,
        private ?string $entidadDepositanteId,
        private EstadoEspecimen $estado,
        private ?string $occurrenceId,
        private ?string $catalogNumber,
        private ?string $oldCode,
        private ?string $cardexLiquidCollectionCode,
        private ?int $individualCount,
        private ?string $individualCountVerbatim,
        private ?string $sex,
        private ?string $lifeStage,
        private ?string $caste,
        private ?string $typeStatus,
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
        private ?string $coordVerbatim,
        private ?string $geodeticDatum,
        private ?float $elevationMinM,
        private ?float $elevationMaxM,
        private ?string $biome,
        private ?string $habitat,
        private ?string $microhabitat,
        private ?string $biogeographicRegion,
        private ?bool $endemic,
        private ?string $dnaNotes,
        private ?string $occurrenceRemarks,
        private ?string $taxonomicNotes,
        private ?string $actaRecepcion,
        private EstadoRevision $estadoRevision,
        private ?string $motivoRevision,
        private array $identificadores,
        private ?int $filaOrigenExcel = null,
    ) {}

    /**
     * @param  array<int, IdentificadorEspecimen|array{tipo: string, valor: string}>  $identificadores
     */
    public static function crear(
        EspecimenId $id,
        string $codigoCatalogo,
        ?string $taxonId,
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
        ?float $elevationMinM = null,
        ?string $biome = null,
        ?string $habitat = null,
        array $identificadores = [],
        ?string $taxonVerbatim = null,
        ?string $muestraId = null,
        ?string $localidadId = null,
        ?string $localidadVerbatim = null,
        ?string $fechaVerbatim = null,
        ?string $fechaColectaFin = null,
        ?string $individualCountVerbatim = null,
        ?string $sex = null,
        ?string $lifeStage = null,
        ?string $caste = null,
        ?string $typeStatus = null,
        ?string $coordVerbatim = null,
        ?float $elevationMaxM = null,
        ?string $microhabitat = null,
        ?string $biogeographicRegion = null,
        ?bool $endemic = null,
        ?string $dnaNotes = null,
        ?string $occurrenceRemarks = null,
        ?string $taxonomicNotes = null,
        ?string $actaRecepcion = null,
        ?string $motivoRevision = null,
        ?int $filaOrigenExcel = null,
    ): self {
        $localidad = trim($localidad);
        $localityName = self::limpiarTexto($localityName) ?? $localidad;
        $codigoCatalogoLimpio = trim($codigoCatalogo);

        return new self(
            id: $id,
            codigoCatalogo: $codigoCatalogoLimpio,
            taxonId: $taxonId,
            taxonVerbatim: self::limpiarTexto($taxonVerbatim),
            muestraId: $muestraId,
            localidad: $localidad,
            localidadId: $localidadId,
            localidadVerbatim: self::limpiarTexto($localidadVerbatim),
            fechaColecta: $fechaColecta,
            fechaVerbatim: self::limpiarTexto($fechaVerbatim),
            fechaColectaFin: self::limpiarTexto($fechaColectaFin),
            colector: trim($colector),
            entidadDepositanteId: $entidadDepositanteId,
            estado: EstadoEspecimen::Disponible,
            occurrenceId: self::limpiarTexto($occurrenceId),
            catalogNumber: self::limpiarTexto($catalogNumber),
            oldCode: self::limpiarTexto($oldCode),
            cardexLiquidCollectionCode: self::limpiarTexto($cardexLiquidCollectionCode),
            individualCount: $individualCount,
            individualCountVerbatim: self::limpiarTexto($individualCountVerbatim),
            sex: self::limpiarTexto($sex),
            lifeStage: self::limpiarTexto($lifeStage),
            caste: self::limpiarTexto($caste),
            typeStatus: self::limpiarTexto($typeStatus),
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
            coordVerbatim: self::limpiarTexto($coordVerbatim),
            geodeticDatum: self::limpiarTexto($geodeticDatum),
            elevationMinM: $elevationMinM,
            elevationMaxM: $elevationMaxM,
            biome: self::limpiarTexto($biome),
            habitat: self::limpiarTexto($habitat),
            microhabitat: self::limpiarTexto($microhabitat),
            biogeographicRegion: self::limpiarTexto($biogeographicRegion),
            endemic: $endemic,
            dnaNotes: self::limpiarTexto($dnaNotes),
            occurrenceRemarks: self::limpiarTexto($occurrenceRemarks),
            taxonomicNotes: self::limpiarTexto($taxonomicNotes),
            actaRecepcion: self::limpiarTexto($actaRecepcion),
            estadoRevision: EstadoRevision::porDefecto(),
            motivoRevision: self::limpiarTexto($motivoRevision),
            identificadores: self::normalizarIdentificadores(
                identificadores: $identificadores,
                codigoCatalogo: $codigoCatalogoLimpio,
                occurrenceId: self::limpiarTexto($occurrenceId),
                catalogNumber: self::limpiarTexto($catalogNumber),
                oldCode: self::limpiarTexto($oldCode),
                cardexLiquidCollectionCode: self::limpiarTexto($cardexLiquidCollectionCode),
            ),
            filaOrigenExcel: $filaOrigenExcel,
        );
    }

    /**
     * @param  IdentificadorEspecimen[]  $identificadores
     */
    public static function reconstituir(
        EspecimenId $id,
        string $codigoCatalogo,
        ?string $taxonId,
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
        ?float $elevationMinM = null,
        ?string $biome = null,
        ?string $habitat = null,
        array $identificadores = [],
        ?string $taxonVerbatim = null,
        ?string $muestraId = null,
        ?string $localidadId = null,
        ?string $localidadVerbatim = null,
        ?string $fechaVerbatim = null,
        ?string $fechaColectaFin = null,
        ?string $individualCountVerbatim = null,
        ?string $sex = null,
        ?string $lifeStage = null,
        ?string $caste = null,
        ?string $typeStatus = null,
        ?string $coordVerbatim = null,
        ?float $elevationMaxM = null,
        ?string $microhabitat = null,
        ?string $biogeographicRegion = null,
        ?bool $endemic = null,
        ?string $dnaNotes = null,
        ?string $occurrenceRemarks = null,
        ?string $taxonomicNotes = null,
        ?string $actaRecepcion = null,
        ?EstadoRevision $estadoRevision = null,
        ?string $motivoRevision = null,
        ?int $filaOrigenExcel = null,
    ): self {
        return new self(
            id: $id,
            codigoCatalogo: $codigoCatalogo,
            taxonId: $taxonId,
            taxonVerbatim: $taxonVerbatim,
            muestraId: $muestraId,
            localidad: $localidad,
            localidadId: $localidadId,
            localidadVerbatim: $localidadVerbatim,
            fechaColecta: $fechaColecta,
            fechaVerbatim: $fechaVerbatim,
            fechaColectaFin: $fechaColectaFin,
            colector: $colector,
            entidadDepositanteId: $entidadDepositanteId,
            estado: $estado,
            occurrenceId: $occurrenceId,
            catalogNumber: $catalogNumber,
            oldCode: $oldCode,
            cardexLiquidCollectionCode: $cardexLiquidCollectionCode,
            individualCount: $individualCount,
            individualCountVerbatim: $individualCountVerbatim,
            sex: $sex,
            lifeStage: $lifeStage,
            caste: $caste,
            typeStatus: $typeStatus,
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
            coordVerbatim: $coordVerbatim,
            geodeticDatum: $geodeticDatum,
            elevationMinM: $elevationMinM,
            elevationMaxM: $elevationMaxM,
            biome: $biome,
            habitat: $habitat,
            microhabitat: $microhabitat,
            biogeographicRegion: $biogeographicRegion,
            endemic: $endemic,
            dnaNotes: $dnaNotes,
            occurrenceRemarks: $occurrenceRemarks,
            taxonomicNotes: $taxonomicNotes,
            actaRecepcion: $actaRecepcion,
            estadoRevision: $estadoRevision ?? EstadoRevision::porDefecto(),
            motivoRevision: $motivoRevision,
            identificadores: $identificadores,
            filaOrigenExcel: $filaOrigenExcel,
        );
    }

    public function filaOrigenExcel(): ?int
    {
        return $this->filaOrigenExcel;
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
        ?float $elevationMinM = null,
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
        $this->elevationMinM = $elevationMinM ?? $this->elevationMinM;
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

    public function enlazarTaxon(TaxonId $taxonId): void
    {
        $this->taxonId = (string) $taxonId;
    }

    public function desvincularTaxon(): void
    {
        $this->taxonId = null;
    }

    public function enlazarLocalidad(LocalidadId $localidadId): void
    {
        $this->localidadId = (string) $localidadId;
    }

    public function enlazarMuestra(MuestraColectaId $muestraId): void
    {
        $this->muestraId = (string) $muestraId;
    }

    public function marcarParaRevision(string $motivo): void
    {
        $motivoNormalizado = trim($motivo);
        if ($motivoNormalizado === '') {
            throw new \InvalidArgumentException('El motivo de revisión es requerido.');
        }
        $this->estadoRevision = EstadoRevision::Pendiente;
        $this->motivoRevision = $motivoNormalizado;
    }

    public function confirmarRevision(): void
    {
        if (! $this->estadoRevision->puedeConfirmarse()) {
            throw new \DomainException(
                "No se puede confirmar la revisión de un espécimen en estado '{$this->estadoRevision->value}'."
            );
        }
        $this->estadoRevision = EstadoRevision::Confirmada;
        $this->motivoRevision = null;
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function id(): EspecimenId
    {
        return $this->id;
    }

    public function codigoCatalogo(): string
    {
        return $this->codigoCatalogo;
    }

    public function taxonId(): ?string
    {
        return $this->taxonId;
    }

    public function taxonVerbatim(): ?string
    {
        return $this->taxonVerbatim;
    }

    public function muestraId(): ?string
    {
        return $this->muestraId;
    }

    public function localidad(): string
    {
        return $this->localidad;
    }

    public function localidadId(): ?string
    {
        return $this->localidadId;
    }

    public function localidadVerbatim(): ?string
    {
        return $this->localidadVerbatim;
    }

    public function fechaColecta(): string
    {
        return $this->fechaColecta;
    }

    public function fechaVerbatim(): ?string
    {
        return $this->fechaVerbatim;
    }

    public function fechaColectaFin(): ?string
    {
        return $this->fechaColectaFin;
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

    public function individualCountVerbatim(): ?string
    {
        return $this->individualCountVerbatim;
    }

    public function sex(): ?string
    {
        return $this->sex;
    }

    public function lifeStage(): ?string
    {
        return $this->lifeStage;
    }

    public function caste(): ?string
    {
        return $this->caste;
    }

    public function typeStatus(): ?string
    {
        return $this->typeStatus;
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

    public function coordVerbatim(): ?string
    {
        return $this->coordVerbatim;
    }

    public function geodeticDatum(): ?string
    {
        return $this->geodeticDatum;
    }

    public function elevationMinM(): ?float
    {
        return $this->elevationMinM;
    }

    public function elevationMaxM(): ?float
    {
        return $this->elevationMaxM;
    }

    public function biome(): ?string
    {
        return $this->biome;
    }

    public function habitat(): ?string
    {
        return $this->habitat;
    }

    public function microhabitat(): ?string
    {
        return $this->microhabitat;
    }

    public function biogeographicRegion(): ?string
    {
        return $this->biogeographicRegion;
    }

    public function endemic(): ?bool
    {
        return $this->endemic;
    }

    public function dnaNotes(): ?string
    {
        return $this->dnaNotes;
    }

    public function occurrenceRemarks(): ?string
    {
        return $this->occurrenceRemarks;
    }

    public function taxonomicNotes(): ?string
    {
        return $this->taxonomicNotes;
    }

    public function actaRecepcion(): ?string
    {
        return $this->actaRecepcion;
    }

    public function estadoRevision(): EstadoRevision
    {
        return $this->estadoRevision;
    }

    public function motivoRevision(): ?string
    {
        return $this->motivoRevision;
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
