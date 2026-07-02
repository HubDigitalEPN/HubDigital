<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

interface EspecimenRepositoryInterface
{
    public function nextIdentity(): EspecimenId;

    public function guardar(Especimen $especimen): void;

    public function buscarPorId(EspecimenId $id): ?Especimen;

    /** @return Especimen[] */
    public function buscarPorEntidadDepositante(string $entidadDepositanteId): array;

    /** @return Especimen[] */
    public function buscarPorLocalidad(string $localidad): array;

    /** @return Especimen[] */
    public function buscarPorEstado(string $estado): array;

    /** @param string[] $taxonIds
     *  @return Especimen[] */
    public function buscarPorTaxonIds(array $taxonIds): array;

    public function buscarPorCodigoCatalogo(string $codigo): ?Especimen;

    /** @return Especimen[] */
    public function buscarPorIdentificador(string $tipo, string $valor): array;

    /** @return Especimen[] */
    public function buscarTodos(): array;

    /**
     * Proyección ligera para la pantalla de asignación a unit trays: NO hidrata
     * entidades de dominio ni carga identificadores, por lo que escala a las 48k+
     * filas del catálogo. Devuelve solo `id`, `codigoCatalogo` y `taxonId`.
     *
     * - `$busqueda`: si se provee, filtra por código de catálogo o nombre
     *   científico del taxón (ILIKE). Vacío/null = vista inicial: primeras filas
     *   por código, EXCLUYENDO los especímenes sin determinar que solo cuelgan
     *   del reino (p. ej. "Animalia"); estos siguen siendo localizables al buscar.
     * - `$limite`: tope de coincidencias devueltas.
     * - `$incluirSiempre`: ids que deben devolverse aunque no coincidan con la
     *   búsqueda ni entren en el límite (los ya asignados al tray en contexto).
     *
     * @param  string[]  $incluirSiempre
     * @return array<int, array{id: string, codigoCatalogo: string, taxonId: ?string}>
     */
    public function buscarParaAsignacion(?string $busqueda, int $limite, array $incluirSiempre = []): array;

    /**
     * Permite la idempotencia del importador: si un espécimen ya fue creado
     * a partir de una fila específica del Excel, no se duplica al re-correr.
     */
    public function existePorFilaOrigen(int $filaOrigenExcel): bool;

    /** Cuenta total de especímenes en el catálogo. */
    public function contarTotal(): int;

    /**
     * Cuenta especímenes que cumplen criterios mínimos GBIF: taxon_id NOT NULL,
     * coordenadas NOT NULL y estado_revision distinto de 'descartada'.
     */
    public function contarPublicablesGbif(): int;

    /** Cuenta especímenes con estado_revision='pendiente' AND motivo NOT NULL. */
    public function contarPendientesRevision(): int;

    /**
     * Inserta múltiples especímenes en una sola transacción para reducir
     * round-trips contra la BD (clave para el importador masivo del catálogo).
     *
     * @param  Especimen[]  $especimenes
     */
    public function guardarBatch(array $especimenes): void;

    /**
     * Busca especímenes marcados para revisión (estado_revision='pendiente'
     * + motivo_revision NOT NULL). Si `$contieneMotivo` se provee, filtra
     * adicionalmente por ILIKE sobre el motivo.
     *
     * @return Especimen[]
     */
    public function buscarParaRevision(?string $contieneMotivo = null, int $limit = 200): array;

    /**
     * Búsqueda multi-filtro AND. Todos los campos son opcionales.
     *
     * @param  array{
     *   taxonIds?: string[],
     *   codigoCatalogo?: string,
     *   occurrenceId?: string,
     *   catalogNumber?: string,
     *   localidad?: string,
     *   colector?: string,
     *   fechaDesde?: string,
     *   fechaHasta?: string,
     *   estado?: string,
     *   estadoRevision?: string,
     *   motivoRevision?: string,
     *   paraRevision?: bool,
     *   limit?: int,
     * }  $filtros
     * @return Especimen[]
     */
    public function buscarConFiltros(array $filtros): array;

    /**
     * Cuenta cuántos especímenes están enganchados a cada `muestra_id` del
     * conjunto provisto. Devuelve mapa `muestra_id => conteo`. Útil para
     * la bandeja de muestras (mostrar cuántos especímenes contiene cada
     * grupo de oldCode).
     *
     * @param  string[]  $muestraIds
     * @return array<string, int>
     */
    public function contarPorMuestraIds(array $muestraIds): array;

    /**
     * Agrupa los `localidad_verbatim` con `localidad_id IS NULL`. Devuelve
     * pares `verbatim => conteo`, ordenados por conteo desc. SQL-side
     * (GROUP BY) — escalable a las 48k filas del Excel.
     *
     * @return array<string, int>
     */
    public function agruparLocalidadVerbatimsPendientes(int $limit, int $offset): array;

    /** Cuenta cuántos `localidad_verbatim` distintos están sin enlazar. */
    public function contarLocalidadVerbatimsPendientes(): int;

    /**
     * Enlaza en bloque todos los especímenes con un `localidad_verbatim` dado
     * y `localidad_id IS NULL` a la localidad canónica. Devuelve número de
     * filas afectadas. UPDATE SQL — escalable.
     */
    public function enlazarLocalidadPorVerbatim(string $verbatim, string $localidadId): int;

    /**
     * Agrupa los `taxon_verbatim` con `taxon_id IS NULL`. Pares
     * `verbatim => conteo`, ordenados por conteo desc, paginado.
     *
     * @return array<string, int>
     */
    public function agruparTaxonVerbatimsPendientes(int $limit, int $offset): array;

    public function contarTaxonVerbatimsPendientes(): int;

    /**
     * Enlaza en bloque todos los especímenes con un `taxon_verbatim` dado y
     * `taxon_id IS NULL` al taxón canónico. Devuelve filas afectadas.
     */
    public function enlazarTaxonPorVerbatim(string $verbatim, string $taxonId): int;

    /**
     * Lista catalog_numbers que aparecen >= $minimo veces. Devuelve pares
     * `catalog_number => conteo` ordenados por conteo desc, paginado.
     *
     * @return array<string, int>
     */
    public function listarCatalogNumbersDuplicados(int $minimo, int $limit, int $offset): array;

    public function contarGruposCatalogNumberDuplicados(int $minimo): int;

    /**
     * Devuelve los especímenes asociados a un conjunto de catalog_numbers
     * (para mostrar el detalle de cada grupo). Una sola query con WHERE IN.
     *
     * @param  string[]  $catalogNumbers
     * @return Especimen[]
     */
    public function buscarPorCatalogNumbersIn(array $catalogNumbers): array;

    /**
     * Marca todos los especímenes con un catalog_number dado como
     * estado_revision='confirmada' y limpia motivo_revision. Útil para
     * resolver duplicados como "eventos distintos".
     */
    public function confirmarRevisionPorCatalogNumber(string $catalogNumber): int;

    /**
     * Marca todos los especímenes con un catalog_number dado como
     * estado_revision='pendiente' con motivo provisto. Para resolver
     * duplicados como "error de catalogación".
     */
    public function marcarRevisionPorCatalogNumber(string $catalogNumber, string $motivo): int;

    /**
     * Agrupa los `fecha_verbatim` cuyo `fecha_colecta` es null/vacío.
     * Pares verbatim => conteo, ordenados por conteo desc, paginado.
     *
     * @return array<string, int>
     */
    public function agruparFechaVerbatimsPendientes(int $limit, int $offset): array;

    public function contarFechaVerbatimsPendientes(): int;

    /**
     * Asigna `fecha_colecta` (y opcionalmente `fecha_colecta_fin` para rangos)
     * a todos los especímenes con un `fecha_verbatim` dado cuya fecha aún no
     * está parseada. Devuelve filas afectadas.
     *
     * Adicionalmente: cuando el motivo de revisión contiene la cadena
     * "fecha_colecta no parseable", el handler debe limpiarlo o marcar el
     * espécimen como confirmada según corresponda.
     */
    public function enlazarFechaPorVerbatim(string $verbatim, string $fechaInicio, ?string $fechaFin = null): int;

    /**
     * Devuelve los `fila_origen_excel` ya persistidos cuyo valor está dentro
     * del set proporcionado. Permite chequear idempotencia en bulk antes de
     * abrir el chunk de inserts.
     *
     * @param  int[]  $filasOrigen
     * @return int[]
     */
    public function filasOrigenExistentes(array $filasOrigen): array;

    /**
     * Lista los especímenes concretos que caen en un grupo `fecha_verbatim`
     * pendiente (aún sin `fecha_colecta`). Permite que el curador vea y elija
     * uno por uno en la bandeja de revisión, en vez de aplicar en bloque.
     *
     * @return Especimen[]
     */
    public function buscarPorFechaVerbatimPendiente(string $verbatim, int $limit = 500): array;

    /**
     * Lista los especímenes de un grupo `taxon_verbatim` pendiente (sin taxon_id).
     *
     * @return Especimen[]
     */
    public function buscarPorTaxonVerbatimPendiente(string $verbatim, int $limit = 500): array;

    /**
     * Lista los especímenes de un grupo `localidad_verbatim` pendiente (sin localidad_id).
     *
     * @return Especimen[]
     */
    public function buscarPorLocalidadVerbatimPendiente(string $verbatim, int $limit = 500): array;

    /**
     * Asigna `fecha_colecta` (y opcional `fecha_colecta_fin`) SOLO a los
     * especímenes indicados por id que aún no tengan fecha. Devuelve el número
     * de filas afectadas. Es la variante selectiva de `enlazarFechaPorVerbatim`.
     *
     * @param  string[]  $ids
     */
    public function enlazarFechaPorIds(array $ids, string $fechaInicio, ?string $fechaFin = null): int;

    /**
     * Enlaza al taxón canónico SOLO los especímenes indicados por id que aún no
     * tengan taxón. Variante selectiva de `enlazarTaxonPorVerbatim`.
     *
     * @param  string[]  $ids
     */
    public function enlazarTaxonPorIds(array $ids, string $taxonId): int;

    /**
     * Enlaza a la localidad canónica SOLO los especímenes indicados por id que
     * aún no tengan localidad. Variante selectiva de `enlazarLocalidadPorVerbatim`.
     *
     * @param  string[]  $ids
     */
    public function enlazarLocalidadPorIds(array $ids, string $localidadId): int;
}
