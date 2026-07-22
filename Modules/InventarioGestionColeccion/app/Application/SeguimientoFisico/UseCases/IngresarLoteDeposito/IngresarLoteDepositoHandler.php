<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCustodia;
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
    ) {}

    public function handle(IngresarLoteDepositoInput $input): IngresarLoteDepositoOutput
    {
        $custodia = EstadoCustodia::from($input->estadoCustodia);

        $codigosExistentes = $this->codigosYaIngresados($input);

        /** @var Especimen[] $nuevos */
        $nuevos = [];
        $codigosCreados = [];
        $omitidos = 0;
        $marcadosParaRevision = 0;

        foreach ($input->filas as $fila) {
            $codigo = $this->codigoCatalogo($input->numeroSolicitud, (int) $fila['indice']);

            if (isset($codigosExistentes[$codigo])) {
                $omitidos++;

                continue;
            }

            $mapeada = $this->mapper->mapear($fila['datosDwC']);

            $especimen = Especimen::crear(
                id: $this->especimenRepo->nextIdentity(),
                codigoCatalogo: $codigo,
                taxonId: null,
                localidad: $mapeada->localidad,
                fechaColecta: $mapeada->fechaColecta,
                colector: $mapeada->colector,
                entidadDepositanteId: $input->entidadDepositanteId,
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
                taxonVerbatim: $mapeada->taxonVerbatim,
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
                actaRecepcion: $input->actaRecepcion,
                // filaOrigenExcel se deja deliberadamente en null: tiene un índice ÚNICO
                // en toda la tabla y pertenece al importador del catálogo, que ya ocupa
                // el rango 1..48856. Reutilizarlo con el número de fila del depósito
                // chocaría con el material heredado. La trazabilidad de un espécimen
                // depositado la dan su codigo_catalogo derivado y su acta_recepcion.
                estadoCustodia: $custodia,
            );

            $motivo = $this->motivoRevision($fila, $mapeada->warnings);

            if ($motivo !== null) {
                $especimen->marcarParaRevision($motivo);
                $marcadosParaRevision++;
            }

            $nuevos[] = $especimen;
            $codigosCreados[] = $codigo;
        }

        foreach (array_chunk($nuevos, self::TAMANIO_CHUNK) as $chunk) {
            $this->especimenRepo->guardarBatch($chunk);
        }

        return new IngresarLoteDepositoOutput(
            especimenesCreados: count($nuevos),
            omitidosPorDuplicado: $omitidos,
            marcadosParaRevision: $marcadosParaRevision,
            codigosCreados: $codigosCreados,
        );
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
