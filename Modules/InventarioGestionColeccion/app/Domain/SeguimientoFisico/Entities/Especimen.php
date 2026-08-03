<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCustodia;
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
        private ?EstadoCustodia $estadoCustodia,
        private ?\DateTimeImmutable $devueltoEn,
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
        private ?string $recordNumber = null,
        private ?string $origin = null,
        private ?string $identifiedBy = null,
        private ?string $dateDetermined = null,
        private ?string $researchPermit = null,
        private ?string $transportPermit = null,
        private ?string $exportImportAuthorization = null,
        private ?string $scientificNameAuthorship = null,
        private ?string $latLonMaxError = null,
        private ?string $clade = null,
        private ?string $identificationQualifier = null,
        private ?string $identificationRemarks = null,
        private ?string $vernacularName = null,
        private ?string $typeNotes = null,
        private ?string $continent = null,
        private ?string $countryCode = null,
        private ?string $localityNotes = null,
        private ?string $localityCode = null,
        private ?string $elevationMaxError = null,
        private ?string $verbatimElevation = null,
        private ?string $verbatimDepth = null,
        private ?string $verbatimLatitude = null,
        private ?string $verbatimLongitude = null,
        private ?string $verbatimCoordinateSystem = null,
        private ?string $verbatimSrs = null,
        private ?string $informationWithheld = null,
        private ?string $priorOwner = null,
        private ?string $locatedAt = null,
        private ?string $iptUpload = null,
        private ?string $recordCreatedBy = null,
        private ?string $responsibleResearcherExport = null,
        private ?string $endemicVerbatim = null,
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
        ?string $recordNumber = null,
        ?string $origin = null,
        ?string $identifiedBy = null,
        ?string $dateDetermined = null,
        ?string $researchPermit = null,
        ?string $transportPermit = null,
        ?string $exportImportAuthorization = null,
        ?string $scientificNameAuthorship = null,
        ?string $latLonMaxError = null,
        ?string $clade = null,
        ?string $identificationQualifier = null,
        ?string $identificationRemarks = null,
        ?string $vernacularName = null,
        ?string $typeNotes = null,
        ?string $continent = null,
        ?string $countryCode = null,
        ?string $localityNotes = null,
        ?string $localityCode = null,
        ?string $elevationMaxError = null,
        ?string $verbatimElevation = null,
        ?string $verbatimDepth = null,
        ?string $verbatimLatitude = null,
        ?string $verbatimLongitude = null,
        ?string $verbatimCoordinateSystem = null,
        ?string $verbatimSrs = null,
        ?string $informationWithheld = null,
        ?string $priorOwner = null,
        ?string $locatedAt = null,
        ?string $iptUpload = null,
        ?string $recordCreatedBy = null,
        ?string $responsibleResearcherExport = null,
        ?string $endemicVerbatim = null,
        ?EstadoCustodia $estadoCustodia = null,
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
            estadoCustodia: $estadoCustodia,
            devueltoEn: null,
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
            recordNumber: self::limpiarTexto($recordNumber),
            origin: self::limpiarTexto($origin),
            identifiedBy: self::limpiarTexto($identifiedBy),
            dateDetermined: self::limpiarTexto($dateDetermined),
            researchPermit: self::limpiarTexto($researchPermit),
            transportPermit: self::limpiarTexto($transportPermit),
            exportImportAuthorization: self::limpiarTexto($exportImportAuthorization),
            scientificNameAuthorship: self::limpiarTexto($scientificNameAuthorship),
            latLonMaxError: self::limpiarTexto($latLonMaxError),
            clade: self::limpiarTexto($clade),
            identificationQualifier: self::limpiarTexto($identificationQualifier),
            identificationRemarks: self::limpiarTexto($identificationRemarks),
            vernacularName: self::limpiarTexto($vernacularName),
            typeNotes: self::limpiarTexto($typeNotes),
            continent: self::limpiarTexto($continent),
            countryCode: self::limpiarTexto($countryCode),
            localityNotes: self::limpiarTexto($localityNotes),
            localityCode: self::limpiarTexto($localityCode),
            elevationMaxError: self::limpiarTexto($elevationMaxError),
            verbatimElevation: self::limpiarTexto($verbatimElevation),
            verbatimDepth: self::limpiarTexto($verbatimDepth),
            verbatimLatitude: self::limpiarTexto($verbatimLatitude),
            verbatimLongitude: self::limpiarTexto($verbatimLongitude),
            verbatimCoordinateSystem: self::limpiarTexto($verbatimCoordinateSystem),
            verbatimSrs: self::limpiarTexto($verbatimSrs),
            informationWithheld: self::limpiarTexto($informationWithheld),
            priorOwner: self::limpiarTexto($priorOwner),
            locatedAt: self::limpiarTexto($locatedAt),
            iptUpload: self::limpiarTexto($iptUpload),
            recordCreatedBy: self::limpiarTexto($recordCreatedBy),
            responsibleResearcherExport: self::limpiarTexto($responsibleResearcherExport),
            endemicVerbatim: self::limpiarTexto($endemicVerbatim),
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
        ?EstadoCustodia $estadoCustodia = null,
        ?\DateTimeImmutable $devueltoEn = null,
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
        ?string $recordNumber = null,
        ?string $origin = null,
        ?string $identifiedBy = null,
        ?string $dateDetermined = null,
        ?string $researchPermit = null,
        ?string $transportPermit = null,
        ?string $exportImportAuthorization = null,
        ?string $scientificNameAuthorship = null,
        ?string $latLonMaxError = null,
        ?string $clade = null,
        ?string $identificationQualifier = null,
        ?string $identificationRemarks = null,
        ?string $vernacularName = null,
        ?string $typeNotes = null,
        ?string $continent = null,
        ?string $countryCode = null,
        ?string $localityNotes = null,
        ?string $localityCode = null,
        ?string $elevationMaxError = null,
        ?string $verbatimElevation = null,
        ?string $verbatimDepth = null,
        ?string $verbatimLatitude = null,
        ?string $verbatimLongitude = null,
        ?string $verbatimCoordinateSystem = null,
        ?string $verbatimSrs = null,
        ?string $informationWithheld = null,
        ?string $priorOwner = null,
        ?string $locatedAt = null,
        ?string $iptUpload = null,
        ?string $recordCreatedBy = null,
        ?string $responsibleResearcherExport = null,
        ?string $endemicVerbatim = null,
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
            estadoCustodia: $estadoCustodia,
            devueltoEn: $devueltoEn,
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
            recordNumber: $recordNumber,
            origin: $origin,
            identifiedBy: $identifiedBy,
            dateDetermined: $dateDetermined,
            researchPermit: $researchPermit,
            transportPermit: $transportPermit,
            exportImportAuthorization: $exportImportAuthorization,
            scientificNameAuthorship: $scientificNameAuthorship,
            latLonMaxError: $latLonMaxError,
            clade: $clade,
            identificationQualifier: $identificationQualifier,
            identificationRemarks: $identificationRemarks,
            vernacularName: $vernacularName,
            typeNotes: $typeNotes,
            continent: $continent,
            countryCode: $countryCode,
            localityNotes: $localityNotes,
            localityCode: $localityCode,
            elevationMaxError: $elevationMaxError,
            verbatimElevation: $verbatimElevation,
            verbatimDepth: $verbatimDepth,
            verbatimLatitude: $verbatimLatitude,
            verbatimLongitude: $verbatimLongitude,
            verbatimCoordinateSystem: $verbatimCoordinateSystem,
            verbatimSrs: $verbatimSrs,
            informationWithheld: $informationWithheld,
            priorOwner: $priorOwner,
            locatedAt: $locatedAt,
            iptUpload: $iptUpload,
            recordCreatedBy: $recordCreatedBy,
            responsibleResearcherExport: $responsibleResearcherExport,
            endemicVerbatim: $endemicVerbatim,
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

    /**
     * El espécimen vuelve de un préstamo con novedad: no queda disponible hasta
     * que el curador resuelva la observación desde el inventario.
     */
    public function marcarObservado(): void
    {
        $this->estado = EstadoEspecimen::Observado;
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

    // ── Edición campo a campo ────────────────────────────────────────────────

    /**
     * Escribe un único campo de la lista blanca de edición masiva.
     *
     * Existe porque `actualizar()` no sirve para esto: cubre 18 de las ~74
     * columnas, obliga a repetir localidad/fecha/colector aunque no se toquen,
     * y sobre todo interpreta `null` como "conserva lo que había", de modo que
     * por esa vía es imposible VACIAR un campo — que es justo la mitad de lo
     * que se pide a una edición masiva.
     *
     * El valor entra y sale en texto: es el formato en el que la bitácora
     * guarda el estado previo, así que redondear por aquí garantiza que lo
     * escrito y lo registrado coinciden.
     *
     * @throws \InvalidArgumentException si el campo no es editable o si se
     *                                   intenta vaciar uno obligatorio
     */
    public function fijarCampoEditable(string $clave, ?string $valor): void
    {
        $campo = RegistroColumnasEspecimen::campoEditable($clave);
        if ($campo === null) {
            throw new \InvalidArgumentException("El campo '{$clave}' no se puede editar en masa.");
        }

        if ($valor === null && ! $campo['admiteVacio']) {
            throw new \InvalidArgumentException("El campo '{$clave}' no puede quedar vacío.");
        }

        if (! property_exists($this, $clave)) {
            throw new \InvalidArgumentException("El campo '{$clave}' no existe en el espécimen.");
        }

        $this->{$clave} = $valor !== null && $campo['tipo'] === RegistroColumnasEspecimen::TIPO_BOOLEANO
            ? $valor === 'true'
            : $valor;
    }

    /** Valor actual de un campo editable, en la misma representación textual. */
    public function valorDeCampoEditable(string $clave): ?string
    {
        if (! RegistroColumnasEspecimen::esEditableEnMasa($clave) || ! property_exists($this, $clave)) {
            throw new \InvalidArgumentException("El campo '{$clave}' no se puede editar en masa.");
        }

        $valor = $this->{$clave};

        if ($valor === null) {
            return null;
        }

        return is_bool($valor) ? ($valor ? 'true' : 'false') : (string) $valor;
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

    // ── Campos plantilla v2 (aditivos) ────────────────────────────────────────

    public function recordNumber(): ?string
    {
        return $this->recordNumber;
    }

    public function origin(): ?string
    {
        return $this->origin;
    }

    public function identifiedBy(): ?string
    {
        return $this->identifiedBy;
    }

    public function dateDetermined(): ?string
    {
        return $this->dateDetermined;
    }

    public function researchPermit(): ?string
    {
        return $this->researchPermit;
    }

    public function transportPermit(): ?string
    {
        return $this->transportPermit;
    }

    public function exportImportAuthorization(): ?string
    {
        return $this->exportImportAuthorization;
    }

    public function scientificNameAuthorship(): ?string
    {
        return $this->scientificNameAuthorship;
    }

    public function latLonMaxError(): ?string
    {
        return $this->latLonMaxError;
    }

    public function clade(): ?string
    {
        return $this->clade;
    }

    public function identificationQualifier(): ?string
    {
        return $this->identificationQualifier;
    }

    public function identificationRemarks(): ?string
    {
        return $this->identificationRemarks;
    }

    public function vernacularName(): ?string
    {
        return $this->vernacularName;
    }

    public function typeNotes(): ?string
    {
        return $this->typeNotes;
    }

    public function continent(): ?string
    {
        return $this->continent;
    }

    public function countryCode(): ?string
    {
        return $this->countryCode;
    }

    public function localityNotes(): ?string
    {
        return $this->localityNotes;
    }

    public function localityCode(): ?string
    {
        return $this->localityCode;
    }

    public function elevationMaxError(): ?string
    {
        return $this->elevationMaxError;
    }

    public function verbatimElevation(): ?string
    {
        return $this->verbatimElevation;
    }

    public function verbatimDepth(): ?string
    {
        return $this->verbatimDepth;
    }

    public function verbatimLatitude(): ?string
    {
        return $this->verbatimLatitude;
    }

    public function verbatimLongitude(): ?string
    {
        return $this->verbatimLongitude;
    }

    public function verbatimCoordinateSystem(): ?string
    {
        return $this->verbatimCoordinateSystem;
    }

    public function verbatimSrs(): ?string
    {
        return $this->verbatimSrs;
    }

    public function informationWithheld(): ?string
    {
        return $this->informationWithheld;
    }

    public function priorOwner(): ?string
    {
        return $this->priorOwner;
    }

    public function locatedAt(): ?string
    {
        return $this->locatedAt;
    }

    public function iptUpload(): ?string
    {
        return $this->iptUpload;
    }

    public function recordCreatedBy(): ?string
    {
        return $this->recordCreatedBy;
    }

    public function responsibleResearcherExport(): ?string
    {
        return $this->responsibleResearcherExport;
    }

    public function endemicVerbatim(): ?string
    {
        return $this->endemicVerbatim;
    }

    /**
     * Régimen de tenencia del ingreso. Null en el material heredado de la carga
     * masiva, que no proviene de un trámite de depósito.
     */
    public function estadoCustodia(): ?EstadoCustodia
    {
        return $this->estadoCustodia;
    }

    public function devueltoEn(): ?\DateTimeImmutable
    {
        return $this->devueltoEn;
    }

    /**
     * Registra que el material volvió a su depositante y salió de la colección.
     *
     * Solo aplica a material bajo custodia temporal: una donación es una cesión
     * definitiva al patrimonio y no se devuelve. La fila se conserva —marcarla, no
     * borrarla— porque el rastro de qué estuvo aquí y cuándo salió forma parte de la
     * documentación de la colección.
     *
     * @throws \DomainException Si el material no es devolutivo.
     */
    public function marcarComoDevuelto(\DateTimeImmutable $devueltoEn): void
    {
        if ($this->estadoCustodia?->esDevolutivo() !== true) {
            $regimen = $this->estadoCustodia?->value ?? 'sin régimen declarado';

            throw new \DomainException(
                "Solo se devuelve el material en custodia temporal; este espécimen está como \"{$regimen}\""
            );
        }

        $this->estadoCustodia = EstadoCustodia::Devuelto;
        $this->devueltoEn = $devueltoEn;
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
