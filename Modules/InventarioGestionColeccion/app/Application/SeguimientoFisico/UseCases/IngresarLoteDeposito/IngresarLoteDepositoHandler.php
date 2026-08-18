<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ResolverTaxonomiaDwCPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverEntidadDepositante\ResolverEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverEntidadDepositante\ResolverEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCustodia;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ProcedenciaDeposito;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;

/**
 * Ingresa a la colección los especímenes de un lote depositado, una vez que la
 * recepción física fue aprobada en GestionPrestamosRecepciones.
 *
 * Reutiliza el mismo mapeo Darwin Core que la carga masiva del catálogo
 * ({@see FilaCatalogoMapper}), de modo que un espécimen depositado y uno importado
 * quedan descritos igual.
 *
 * Dos decisiones que conviene no perder de vista:
 *
 * 1. **La taxonomía entra por verbatim, no por `taxonId`.** Un depósito puede traer
 *    especies aún no catalogadas —el módulo de origen tiene un caso de uso entero
 *    para justificarlas—, así que exigir un taxón existente rechazaría ingresos
 *    legítimos. Se guarda `taxonVerbatim` y la conciliación posterior la hace la
 *    maquinaria de verbatims que ya existe en este módulo.
 * 2. **La idempotencia se resuelve aquí, no en la base.** `taxonomia.especimenes`
 *    solo tiene clave primaria sobre `id`, así que nada impide insertar dos veces el
 *    mismo espécimen si el job se reintenta. El código de catálogo derivado del
 *    depósito es determinista y sirve de clave para detectar lo ya ingresado.
 */
final class IngresarLoteDepositoHandler
{
    /** Tamaño de lote para el guardado masivo, alineado con el importador del catálogo. */
    private const TAMANIO_CHUNK = 500;

    /** Estados de la matriz que aún no tienen el visto bueno taxonómico del curador. */
    private const ESTADOS_SIN_VALIDAR = ['Pendiente', 'Validación Manual por Curaduría'];

    /**
     * Avisos del mapeo que no son anomalía en un depósito.
     *
     * El `occurrenceID` lo asigna la colección al publicar, no el depositante, así que
     * su ausencia es lo normal en una matriz de depósito. Si se tratara como anomalía,
     * el 100 % de los especímenes ingresados caería en la cola de revisión y ahogaría
     * lo que sí importa: la taxonomía sin validar.
     */
    private const AVISOS_ESPERADOS_EN_DEPOSITO = ['occurrence_id ausente'];

    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly FilaCatalogoMapper $mapper,
        private readonly ResolverEntidadDepositanteHandler $resolverEntidad,
        private readonly ResolverTaxonomiaDwCPort $resolverTaxonomia,
    ) {}

    public function handle(IngresarLoteDepositoInput $input): IngresarLoteDepositoOutput
    {
        $custodia = self::custodiaDesdeIngreso($input->estadoCustodia);

        $entidadDepositanteId = $this->resolverDepositante($input);

        $codigosExistentes = $this->codigosYaIngresados($input);
        $registrosExistentes = $this->registrosYaIngresados($input);

        /** @var Especimen[] $nuevos */
        $nuevos = [];
        $codigosCreados = [];
        $omitidos = 0;
        $marcadosParaRevision = 0;
        /** @var ResultadoFilaDeposito[] $resultados */
        $resultados = [];

        foreach ($input->filas as $fila) {
            $codigo = $this->codigoCatalogo($input->numeroSolicitud, (int) $fila['indice']);
            $registroId = $this->registroId($fila);

            // El uuid del registro es AUTORITATIVO cuando viaja: si lo hay, decide él solo.
            //
            // No basta con consultarlo antes que el código derivado: hay que ignorar el
            // código por completo. Los dos criterios juntos hacen daño en los dos sentidos
            // al reordenarse la matriz, porque el código depende de la posición. Una fila
            // que se desplaza hereda el código de otra y se duplicaría; una fila nueva
            // hereda el código de la que ocupaba su puesto y se descartaría en silencio,
            // que es lo grave: material declarado que nunca llega a la colección.
            $yaIngresada = $registroId !== null
                ? isset($registrosExistentes[$registroId])
                : isset($codigosExistentes[$codigo]);

            if ($yaIngresada) {
                $omitidos++;
                $resultados[] = new ResultadoFilaDeposito(
                    indiceMatriz: (int) $fila['indice'],
                    registroId: $registroId,
                    // El espécimen que ya existía, para que el otro módulo pueda anotar el
                    // vínculo aunque esta pasada no haya creado nada.
                    especimenId: $registroId !== null ? ($registrosExistentes[$registroId] ?? null) : null,
                    codigoCatalogo: $codigo,
                    resultado: ResultadoFilaDeposito::OMITIDO,
                );

                continue;
            }

            $mapeada = $this->mapper->mapear($fila['datosDwC']);

            // La identificación que vale es la que quedó tras la revisión de la matriz:
            // si el curador aceptó una corrección tipográfica, la colección recibe el
            // nombre corregido y no el que declaró el depositante. El registro Darwin
            // Core original no se toca — sigue en `datosDwC` y en `dwc_extra`.
            $taxonVerbatim = $this->nombreCanonico($fila) ?? $mapeada->taxonVerbatim;

            $especimen = Especimen::crear(
                id: $this->especimenRepo->nextIdentity(),
                codigoCatalogo: $codigo,
                taxonId: $this->taxonCanonico($fila, $mapeada),
                localidad: $mapeada->localidad,
                fechaColecta: $mapeada->fechaColecta,
                colector: $mapeada->colector,
                entidadDepositanteId: $entidadDepositanteId,
                occurrenceId: $mapeada->occurrenceId,
                catalogNumber: $mapeada->catalogNumber,
                oldCode: $mapeada->oldCode,
                cardexLiquidCollectionCode: $mapeada->cardexLiquidCollectionCode,
                individualCount: $mapeada->individualCount,
                preparations: $mapeada->preparations,
                disposition: $mapeada->disposition,
                occurrenceStatus: $mapeada->occurrenceStatus,
                specimenNotes: $mapeada->specimenNotes,
                country: $mapeada->country,
                stateProvince: $mapeada->stateProvince,
                municipality: $mapeada->municipality,
                localityName: $mapeada->localityName,
                decimalLatitude: $mapeada->decimalLatitude,
                decimalLongitude: $mapeada->decimalLongitude,
                geodeticDatum: $mapeada->geodeticDatum,
                elevationMinM: $mapeada->elevationMinM,
                biome: $mapeada->biome,
                habitat: $mapeada->habitat,
                taxonVerbatim: $taxonVerbatim,
                localidadVerbatim: $mapeada->localidadVerbatim,
                fechaVerbatim: $mapeada->fechaVerbatim,
                fechaColectaFin: $mapeada->fechaColectaFin,
                individualCountVerbatim: $mapeada->individualCountVerbatim,
                sex: $mapeada->sex,
                lifeStage: $mapeada->lifeStage,
                caste: $mapeada->caste,
                typeStatus: $mapeada->typeStatus,
                coordVerbatim: $mapeada->coordVerbatim,
                elevationMaxM: $mapeada->elevationMaxM,
                microhabitat: $mapeada->microhabitat,
                biogeographicRegion: $mapeada->biogeographicRegion,
                endemic: $mapeada->endemic,
                dnaNotes: $mapeada->dnaNotes,
                occurrenceRemarks: $mapeada->occurrenceRemarks,
                taxonomicNotes: $mapeada->taxonomicNotes,
                actaRecepcion: $input->codigoQrLote,
                // filaOrigenExcel se deja deliberadamente en null: tiene un índice ÚNICO
                // en toda la tabla y pertenece al importador del catálogo, que ya ocupa
                // el rango 1..48856. Reutilizarlo con el número de fila del depósito
                // chocaría con el material heredado. La trazabilidad de un espécimen
                // depositado la da ahora su procedencia de depósito.
                //
                // Campos de la plantilla v2. Estaban mapeados desde el principio y no se
                // pasaban: el ingreso por depósito llegó a la colección con permisos,
                // determinador y número de campo en blanco mientras la matriz sí los
                // traía. El importador del catálogo sí los pasaba, y por eso la
                // divergencia no saltaba a la vista.
                recordNumber: $mapeada->recordNumber,
                origin: $mapeada->origin,
                identifiedBy: $mapeada->identifiedBy,
                dateDetermined: $mapeada->dateDetermined,
                // El permiso ampara al depósito entero y vive en el trámite. Si una
                // matriz antigua todavía trae el suyo por fila, se respeta: es lo que
                // declaró su depositante en su momento.
                researchPermit: $mapeada->researchPermit ?? $input->permisoRecoleccion,
                transportPermit: $mapeada->transportPermit ?? $input->permisoMovilizacion,
                exportImportAuthorization: $mapeada->exportImportAuthorization,
                scientificNameAuthorship: $mapeada->scientificNameAuthorship,
                latLonMaxError: $mapeada->latLonMaxError,
                clade: $mapeada->clade,
                identificationQualifier: $mapeada->identificationQualifier,
                identificationRemarks: $mapeada->identificationRemarks,
                vernacularName: $mapeada->vernacularName,
                typeNotes: $mapeada->typeNotes,
                continent: $mapeada->continent,
                countryCode: $mapeada->countryCode,
                localityNotes: $mapeada->localityNotes,
                localityCode: $mapeada->localityCode,
                elevationMaxError: $mapeada->elevationMaxError,
                verbatimElevation: $mapeada->verbatimElevation,
                verbatimDepth: $mapeada->verbatimDepth,
                verbatimLatitude: $mapeada->verbatimLatitude,
                verbatimLongitude: $mapeada->verbatimLongitude,
                verbatimCoordinateSystem: $mapeada->verbatimCoordinateSystem,
                verbatimSrs: $mapeada->verbatimSrs,
                informationWithheld: $mapeada->informationWithheld,
                priorOwner: $mapeada->priorOwner,
                locatedAt: $mapeada->locatedAt,
                iptUpload: $mapeada->iptUpload,
                recordCreatedBy: $mapeada->recordCreatedBy ?? $input->registradoPor,
                responsibleResearcherExport: $mapeada->responsibleResearcherExport,
                endemicVerbatim: $mapeada->endemicVerbatim,
                estadoCustodia: $custodia,
                darwinCoreExtendido: $mapeada->darwinCoreExtendido(),
            );

            $this->registrarProcedencia($especimen, $input, $fila, $registroId);

            $motivo = $this->motivoRevision($fila, $mapeada->warnings);

            if ($motivo !== null) {
                $especimen->marcarParaRevision($motivo);
                $marcadosParaRevision++;
            }

            $nuevos[] = $especimen;
            $codigosCreados[] = $codigo;

            $resultados[] = new ResultadoFilaDeposito(
                indiceMatriz: (int) $fila['indice'],
                registroId: $registroId,
                especimenId: (string) $especimen->id(),
                codigoCatalogo: $codigo,
                resultado: $motivo === null
                    ? ResultadoFilaDeposito::CREADO
                    : ResultadoFilaDeposito::CREADO_PARA_REVISION,
                motivoRevision: $motivo,
            );
        }

        foreach (array_chunk($nuevos, self::TAMANIO_CHUNK) as $chunk) {
            $this->especimenRepo->guardarBatch($chunk);
        }

        return new IngresarLoteDepositoOutput(
            especimenesCreados: count($nuevos),
            omitidosPorDuplicado: $omitidos,
            marcadosParaRevision: $marcadosParaRevision,
            codigosCreados: $codigosCreados,
            resultados: $resultados,
        );
    }

    /**
     * Taxón canónico al que se engancha la fila, si procede engancharla.
     *
     * **Solo para lo que el curador ya validó.** Resolver la jerarquía crea taxones que
     * no existían, y el árbol taxonómico del museo es patrimonio compartido: dejar que un
     * depósito sin revisar dé de alta nombres ahí lo contaminaría. El gate de validación
     * del módulo de recepciones es precisamente lo que autoriza a tocarlo.
     *
     * Lo que no se resuelve no se pierde: entra con `taxon_verbatim`, queda pendiente de
     * revisión y lo concilia después la bandeja de verbatims, que ya existe para eso.
     *
     * @param  array<string, mixed>  $fila
     */
    private function taxonCanonico(array $fila, $mapeada): ?string
    {
        if (in_array($fila['estadoRegistro'], self::ESTADOS_SIN_VALIDAR, true)) {
            return null;
        }

        $jerarquia = $mapeada->darwinCoreExtendido();

        if (! $jerarquia->tieneJerarquia()) {
            return null;
        }

        return $this->resolverTaxonomia->resolver($jerarquia->jerarquiaParaResolucion());
    }

    /**
     * Entidad depositante a la que pertenece este lote.
     *
     * Se resuelve una sola vez por lote, no por fila: todo el material de un depósito
     * viene del mismo depositante. Si el lote no trae quién lo depositó se respeta lo
     * que llegue en `entidadDepositanteId`, que puede ser null.
     */
    private function resolverDepositante(IngresarLoteDepositoInput $input): ?string
    {
        $nombre = trim((string) $input->depositanteNombre);
        $institucion = trim((string) $input->depositanteInstitucion);

        if ($nombre === '' && $institucion === '') {
            return $input->entidadDepositanteId;
        }

        return $this->resolverEntidad->handle(new ResolverEntidadDepositanteInput(
            nombrePersona: $nombre,
            institucion: $institucion === '' ? null : $institucion,
            email: $input->depositanteEmail,
        ))->entidadId;
    }

    /**
     * Ata el espécimen recién creado al trámite y a la fila que lo declararon.
     *
     * Sin `solicitudDepositoId` no hay junta fuerte que registrar —los escenarios que
     * arman filas a mano no lo pasan—, pero el número de solicitud siempre viaja y basta
     * para dejar rastro de la procedencia.
     *
     * @param  array<string, mixed>  $fila
     */
    private function registrarProcedencia(
        Especimen $especimen,
        IngresarLoteDepositoInput $input,
        array $fila,
        ?string $registroId,
    ): void {
        $procedencia = ProcedenciaDeposito::parcial(
            registroId: $registroId,
            solicitudId: $input->solicitudDepositoId,
            indiceMatriz: (int) $fila['indice'],
            numeroSolicitud: $input->numeroSolicitud,
            tipoTramite: $input->tipoTramite,
            ingresadoEn: $input->recibidoEn ?? new \DateTimeImmutable,
        );

        if ($procedencia !== null) {
            $especimen->registrarProcedenciaDeposito($procedencia);
        }
    }

    /**
     * Filas de este lote que ya produjeron un espécimen, por su uuid de registro.
     *
     * @return array<string, string>
     */
    private function registrosYaIngresados(IngresarLoteDepositoInput $input): array
    {
        $ids = [];

        foreach ($input->filas as $fila) {
            $registroId = $this->registroId($fila);

            if ($registroId !== null) {
                $ids[] = $registroId;
            }
        }

        return $ids === [] ? [] : $this->especimenRepo->registrosDepositoExistentes($ids);
    }

    /** @param array<string, mixed> $fila */
    private function registroId(array $fila): ?string
    {
        $registroId = $fila['registroId'] ?? null;

        return is_string($registroId) && trim($registroId) !== '' ? trim($registroId) : null;
    }

    /**
     * Nombre científico ya revisado que trae la fila, si el contrato lo incluye.
     *
     * Opcional a propósito: los escenarios que construyen filas a mano no tienen por qué
     * conocer este campo, y sin él se cae al nombre del registro Darwin Core.
     *
     * @param  array<string, mixed>  $fila
     */
    private function nombreCanonico(array $fila): ?string
    {
        $nombre = $fila['nombreCientificoCanonico'] ?? null;

        if (! is_string($nombre) || trim($nombre) === '') {
            return null;
        }

        return trim($nombre);
    }

    /**
     * Traduce el régimen que emite el módulo de recepciones al de esta colección.
     *
     * Los dos vocabularios coinciden hoy palabra por palabra, y precisamente por eso
     * conviene que la traducción sea explícita: el contrato viaja como `string` entre
     * bounded contexts, así que si el otro módulo renombra uno de sus casos, nada avisa
     * en compilación. Con `EstadoCustodia::from()` a secas el fallo salía como un error
     * de enum ilegible en medio del ingreso; aquí sale nombrando el valor y de dónde
     * viene.
     *
     * `Devuelto` no se acepta: es un estado terminal al que se llega devolviendo el
     * material, nunca ingresándolo.
     *
     * @throws \InvalidArgumentException Si el régimen no es uno de los tres de ingreso.
     */
    public static function custodiaDesdeIngreso(string $estadoColeccion): EstadoCustodia
    {
        return match ($estadoColeccion) {
            'Temporal' => EstadoCustodia::Temporal,
            'Permanente' => EstadoCustodia::Permanente,
            'Cuarentena' => EstadoCustodia::Cuarentena,
            default => throw new \InvalidArgumentException(
                sprintf(
                    'Régimen de custodia "%s" desconocido al ingresar un lote depositado; se esperaba Temporal, Permanente o Cuarentena.',
                    $estadoColeccion,
                )
            ),
        };
    }

    /**
     * Código interno derivado del depósito: MEPN-INV-DEP-00002-0001.
     *
     * Determinista a propósito — es lo que permite reejecutar la ingesta sin duplicar.
     */
    public static function codigoCatalogoPara(string $numeroSolicitud, int $indice): string
    {
        return sprintf('%s-%04d', $numeroSolicitud, $indice);
    }

    private function codigoCatalogo(string $numeroSolicitud, int $indice): string
    {
        return self::codigoCatalogoPara($numeroSolicitud, $indice);
    }

    /**
     * Códigos de este depósito que ya están en la colección, indexados para consulta directa.
     *
     * @return array<string, true>
     */
    private function codigosYaIngresados(IngresarLoteDepositoInput $input): array
    {
        $codigos = array_map(
            fn (array $fila): string => $this->codigoCatalogo($input->numeroSolicitud, (int) $fila['indice']),
            $input->filas,
        );

        // Por `codigo_catalogo`, no por `catalog_number`: son columnas distintas y
        // buscarPorCatalogNumbersIn() consulta la segunda, con lo que no casaría nunca.
        return array_fill_keys($this->especimenRepo->codigosCatalogoExistentes($codigos), true);
    }

    /**
     * Motivo por el que el curador debe revisar el registro, si lo hay.
     *
     * Se combinan dos fuentes: la validación taxonómica que trae la matriz del módulo
     * de depósitos y los avisos de calidad de dato que levantó el mapeo Darwin Core
     * (fecha no parseable, conteo no numérico, coordenadas fuera de rango), descartando
     * los que en un depósito son esperables.
     *
     * @param  array{estadoRegistro: string, motivoJustificacion?: string|null}  $fila
     * @param  string[]  $avisosDelMapeo
     */
    private function motivoRevision(array $fila, array $avisosDelMapeo): ?string
    {
        $motivos = [];

        if (in_array($fila['estadoRegistro'], self::ESTADOS_SIN_VALIDAR, true)) {
            $motivos[] = 'taxonomía sin validar en el depósito: '.$fila['estadoRegistro'];

            if (! empty($fila['motivoJustificacion'])) {
                $motivos[] = 'justificación del depositante: '.$fila['motivoJustificacion'];
            }
        }

        foreach ($avisosDelMapeo as $aviso) {
            if (! in_array($aviso, self::AVISOS_ESPERADOS_EN_DEPOSITO, true)) {
                $motivos[] = $aviso;
            }
        }

        return $motivos === [] ? null : implode('; ', $motivos);
    }
}
