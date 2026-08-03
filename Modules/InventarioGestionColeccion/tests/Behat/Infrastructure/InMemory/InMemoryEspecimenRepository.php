<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\MapeadorFilaEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class InMemoryEspecimenRepository implements EspecimenRepositoryInterface
{
    /** @var array<string, Especimen> */
    private array $store = [];

    public function nextIdentity(): EspecimenId
    {
        return EspecimenId::generar();
    }

    public function guardar(Especimen $especimen): void
    {
        $this->store[(string) $especimen->id()] = $especimen;
    }

    public function buscarPorId(EspecimenId $id): ?Especimen
    {
        return $this->store[(string) $id] ?? null;
    }

    /** @return Especimen[] */
    public function buscarPorIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (isset($this->store[(string) $id])) {
                $out[] = $this->store[(string) $id];
            }
        }

        return $out;
    }

    /** @return Especimen[] */
    public function buscarPorEntidadDepositante(string $entidadDepositanteId): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Especimen $e) => $e->entidadDepositanteId() === $entidadDepositanteId
        ));
    }

    /** @return Especimen[] */
    public function buscarPorLocalidad(string $localidad): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Especimen $e) => stripos($e->localidad(), $localidad) !== false
        ));
    }

    /** @return Especimen[] */
    public function buscarPorEstado(string $estado): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Especimen $e) => $e->estado()->value === $estado
        ));
    }

    /** @param string[] $taxonIds
     *  @return Especimen[] */
    public function buscarPorTaxonIds(array $taxonIds): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Especimen $e) => in_array($e->taxonId(), $taxonIds, true)
        ));
    }

    public function buscarPorCodigoCatalogo(string $codigo): ?Especimen
    {
        foreach ($this->store as $especimen) {
            if ($especimen->codigoCatalogo() === $codigo) {
                return $especimen;
            }
        }

        return null;
    }

    /** @return Especimen[] */
    public function buscarPorIdentificador(string $tipo, string $valor): array
    {
        return array_values(array_filter(
            $this->store,
            function (Especimen $e) use ($tipo, $valor): bool {
                foreach ($e->identificadores() as $identificador) {
                    if ($identificador->tipo()->value === $tipo && stripos($identificador->valor(), $valor) !== false) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /** @return Especimen[] */
    public function buscarTodos(): array
    {
        return array_values($this->store);
    }

    /**
     * @param  string[]  $incluirSiempre
     * @return array<int, array{id: string, codigoCatalogo: string, taxonId: ?string}>
     */
    public function buscarParaAsignacion(?string $busqueda, int $limite, array $incluirSiempre = []): array
    {
        $busqueda = $busqueda !== null ? trim($busqueda) : '';

        $coincide = function (Especimen $e) use ($busqueda): bool {
            if ($busqueda === '') {
                return true;
            }

            return stripos($e->codigoCatalogo(), $busqueda) !== false;
        };

        $forzados = array_flip($incluirSiempre);
        $resultado = [];

        foreach ($this->store as $e) {
            $id = (string) $e->id();
            $esForzado = isset($forzados[$id]);

            if (! $esForzado && (! $coincide($e) || count($resultado) >= $limite)) {
                continue;
            }

            $resultado[$id] = [
                'id' => $id,
                'codigoCatalogo' => $e->codigoCatalogo(),
                'taxonId' => $e->taxonId(),
            ];
        }

        return array_values($resultado);
    }

    public function existePorFilaOrigen(int $filaOrigenExcel): bool
    {
        foreach ($this->store as $especimen) {
            if ($especimen->filaOrigenExcel() === $filaOrigenExcel) {
                return true;
            }
        }

        return false;
    }

    public function contarTotal(): int
    {
        return count($this->store);
    }

    public function contarPublicablesGbif(): int
    {
        $c = 0;
        foreach ($this->store as $e) {
            if ($e->taxonId() !== null
                && $e->decimalLatitude() !== null
                && $e->decimalLongitude() !== null
                && $e->estadoRevision()->value !== 'descartada') {
                $c++;
            }
        }

        return $c;
    }

    public function contarPendientesRevision(): int
    {
        $c = 0;
        foreach ($this->store as $e) {
            if ($e->estadoRevision()->value === 'pendiente' && $e->motivoRevision() !== null) {
                $c++;
            }
        }

        return $c;
    }

    /** @return array<string, int> */
    public function agruparLocalidadVerbatimsPendientes(int $limit, int $offset): array
    {
        $grupos = [];
        foreach ($this->store as $e) {
            if ($e->localidadId() !== null) {
                continue;
            }
            $v = $e->localidadVerbatim();
            if ($v === null || $v === '') {
                continue;
            }
            $grupos[$v] = ($grupos[$v] ?? 0) + 1;
        }
        arsort($grupos);

        return array_slice($grupos, $offset, $limit, true);
    }

    public function contarLocalidadVerbatimsPendientes(): int
    {
        $unicos = [];
        foreach ($this->store as $e) {
            if ($e->localidadId() !== null) {
                continue;
            }
            $v = $e->localidadVerbatim();
            if ($v === null || $v === '') {
                continue;
            }
            $unicos[$v] = true;
        }

        return count($unicos);
    }

    public function enlazarLocalidadPorVerbatim(string $verbatim, string $localidadId): int
    {
        $contador = 0;
        $localidad = LocalidadId::desde($localidadId);
        foreach ($this->store as $e) {
            if ($e->localidadId() !== null) {
                continue;
            }
            if ($e->localidadVerbatim() !== $verbatim) {
                continue;
            }
            $e->enlazarLocalidad($localidad);
            $contador++;
        }

        return $contador;
    }

    /** @return array<string, int> */
    public function agruparTaxonVerbatimsPendientes(int $limit, int $offset): array
    {
        $grupos = [];
        foreach ($this->store as $e) {
            if ($e->taxonId() !== null) {
                continue;
            }
            $v = $e->taxonVerbatim();
            if ($v === null || $v === '') {
                continue;
            }
            $grupos[$v] = ($grupos[$v] ?? 0) + 1;
        }
        arsort($grupos);

        return array_slice($grupos, $offset, $limit, true);
    }

    public function contarTaxonVerbatimsPendientes(): int
    {
        $unicos = [];
        foreach ($this->store as $e) {
            if ($e->taxonId() !== null) {
                continue;
            }
            $v = $e->taxonVerbatim();
            if ($v === null || $v === '') {
                continue;
            }
            $unicos[$v] = true;
        }

        return count($unicos);
    }

    public function enlazarTaxonPorVerbatim(string $verbatim, string $taxonId): int
    {
        $contador = 0;
        $taxon = TaxonId::desde($taxonId);
        foreach ($this->store as $e) {
            if ($e->taxonId() !== null) {
                continue;
            }
            if ($e->taxonVerbatim() !== $verbatim) {
                continue;
            }
            $e->enlazarTaxon($taxon);
            $contador++;
        }

        return $contador;
    }

    /** @return array<string, int> */
    public function listarCatalogNumbersDuplicados(int $minimo, int $limit, int $offset): array
    {
        $grupos = [];
        foreach ($this->store as $e) {
            $cn = $e->catalogNumber();
            if ($cn === null || $cn === '') {
                continue;
            }
            $grupos[$cn] = ($grupos[$cn] ?? 0) + 1;
        }
        $grupos = array_filter($grupos, fn ($t) => $t >= $minimo);
        arsort($grupos);

        return array_slice($grupos, $offset, $limit, true);
    }

    public function contarGruposCatalogNumberDuplicados(int $minimo): int
    {
        $grupos = [];
        foreach ($this->store as $e) {
            $cn = $e->catalogNumber();
            if ($cn === null || $cn === '') {
                continue;
            }
            $grupos[$cn] = ($grupos[$cn] ?? 0) + 1;
        }

        return count(array_filter($grupos, fn ($t) => $t >= $minimo));
    }

    /** @param string[] $catalogNumbers
     *  @return Especimen[] */
    public function buscarPorCatalogNumbersIn(array $catalogNumbers): array
    {
        $set = array_flip($catalogNumbers);
        $items = array_values(array_filter(
            $this->store,
            fn (Especimen $e) => $e->catalogNumber() !== null && isset($set[$e->catalogNumber()])
        ));
        usort($items, function (Especimen $a, Especimen $b): int {
            $cmp = strcmp((string) $a->catalogNumber(), (string) $b->catalogNumber());

            return $cmp !== 0 ? $cmp : strcmp($a->fechaColecta(), $b->fechaColecta());
        });

        return $items;
    }

    public function confirmarRevisionPorCatalogNumber(string $catalogNumber): int
    {
        $contador = 0;
        foreach ($this->store as $e) {
            if ($e->catalogNumber() !== $catalogNumber) {
                continue;
            }
            if ($e->estadoRevision()->puedeConfirmarse()) {
                $e->confirmarRevision();
            }
            $contador++;
        }

        return $contador;
    }

    public function marcarRevisionPorCatalogNumber(string $catalogNumber, string $motivo): int
    {
        $contador = 0;
        foreach ($this->store as $e) {
            if ($e->catalogNumber() !== $catalogNumber) {
                continue;
            }
            $e->marcarParaRevision($motivo);
            $contador++;
        }

        return $contador;
    }

    public function confirmarRevisionPorIds(array $ids): int
    {
        $contador = 0;
        foreach ($this->store as $e) {
            if (! in_array((string) $e->id(), $ids, true)) {
                continue;
            }
            if ($e->estadoRevision()->puedeConfirmarse()) {
                $e->confirmarRevision();
            }
            $contador++;
        }

        return $contador;
    }

    public function marcarRevisionPorIds(array $ids, string $motivo): int
    {
        $contador = 0;
        foreach ($this->store as $e) {
            if (! in_array((string) $e->id(), $ids, true)) {
                continue;
            }
            $e->marcarParaRevision($motivo);
            $contador++;
        }

        return $contador;
    }

    /** @return array<string, int> */
    public function agruparFechaVerbatimsPendientes(int $limit, int $offset): array
    {
        $grupos = [];
        foreach ($this->store as $e) {
            $v = $e->fechaVerbatim();
            if ($v === null || $v === '') {
                continue;
            }
            if ($e->fechaColecta() !== '' && $e->fechaColecta() !== null) {
                continue;
            }
            $grupos[$v] = ($grupos[$v] ?? 0) + 1;
        }
        arsort($grupos);

        return array_slice($grupos, $offset, $limit, true);
    }

    public function contarFechaVerbatimsPendientes(): int
    {
        $unicos = [];
        foreach ($this->store as $e) {
            $v = $e->fechaVerbatim();
            if ($v === null || $v === '') {
                continue;
            }
            if ($e->fechaColecta() !== '' && $e->fechaColecta() !== null) {
                continue;
            }
            $unicos[$v] = true;
        }

        return count($unicos);
    }

    public function contarTaxonSinDatoOrigen(): int
    {
        $n = 0;
        foreach ($this->store as $e) {
            $v = $e->taxonVerbatim();
            if ($e->taxonId() === null && ($v === null || $v === '')) {
                $n++;
            }
        }

        return $n;
    }

    public function contarFechaSinDatoOrigen(): int
    {
        $n = 0;
        foreach ($this->store as $e) {
            $v = $e->fechaVerbatim();
            if (($e->fechaColecta() === '' || $e->fechaColecta() === null) && ($v === null || $v === '')) {
                $n++;
            }
        }

        return $n;
    }

    public function contarLocalidadSinDatoOrigen(): int
    {
        $n = 0;
        foreach ($this->store as $e) {
            $v = $e->localidadVerbatim();
            if ($e->localidadId() === null && ($v === null || $v === '')) {
                $n++;
            }
        }

        return $n;
    }

    public function enlazarFechaPorVerbatim(string $verbatim, string $fechaInicio, ?string $fechaFin = null): int
    {
        // La entidad no expone un setter directo para fecha_colecta; en InMemory
        // este método es informativo (devuelve cuántos habrían sido afectados).
        // La rama Eloquent ejecuta el UPDATE real. Tests de integración cubren el
        // path real con la BD.
        $contador = 0;
        foreach ($this->store as $e) {
            if ($e->fechaVerbatim() !== $verbatim) {
                continue;
            }
            if ($e->fechaColecta() !== '' && $e->fechaColecta() !== null) {
                continue;
            }
            $contador++;
        }

        return $contador;
    }

    /** @param string[] $muestraIds
     *  @return array<string, int> */
    public function contarPorMuestraIds(array $muestraIds): array
    {
        $out = [];
        $set = array_flip($muestraIds);
        foreach ($this->store as $e) {
            $mid = $e->muestraId();
            if ($mid === null || ! isset($set[$mid])) {
                continue;
            }
            $out[$mid] = ($out[$mid] ?? 0) + 1;
        }

        return $out;
    }

    public function localidadRepresentativaPorMuestraIds(array $muestraIds): array
    {
        $set = array_flip($muestraIds);
        $conteos = []; // mid => [localidad => count]
        foreach ($this->store as $e) {
            $mid = $e->muestraId();
            $loc = trim((string) $e->localidad());
            if ($mid === null || ! isset($set[$mid]) || $loc === '') {
                continue;
            }
            $conteos[$mid][$loc] = ($conteos[$mid][$loc] ?? 0) + 1;
        }

        $out = [];
        foreach ($conteos as $mid => $locs) {
            arsort($locs);
            $out[$mid] = (string) array_key_first($locs);
        }

        return $out;
    }

    public function buscarPorMuestraId(string $muestraId, int $limite = 500): array
    {
        $out = [];
        foreach ($this->store as $e) {
            if ($e->muestraId() === $muestraId) {
                $out[] = $e;
            }
        }

        return array_slice($out, 0, $limite);
    }

    /** @return Especimen[] */
    public function buscarParaRevision(?string $contieneMotivo = null, int $limit = 200): array
    {
        $resultado = [];
        $contiene = $contieneMotivo !== null ? trim($contieneMotivo) : null;
        foreach ($this->store as $e) {
            if ($e->estadoRevision()->value !== 'pendiente') {
                continue;
            }
            $motivo = $e->motivoRevision();
            if ($motivo === null) {
                continue;
            }
            if ($contiene !== null && $contiene !== '' && stripos($motivo, $contiene) === false) {
                continue;
            }
            $resultado[] = $e;
            if (count($resultado) >= $limit) {
                break;
            }
        }

        return $resultado;
    }

    /** @return Especimen[] */
    public function buscarConFiltros(array $filtros): array
    {
        $items = $this->filtrarEnMemoria($filtros);

        $limit = (int) ($filtros['limit'] ?? 200);
        $offset = max(0, (int) ($filtros['offset'] ?? 0));

        return array_slice($items, $offset, $limit);
    }

    public function contarConFiltros(array $filtros): int
    {
        return count($this->filtrarEnMemoria($filtros));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{especimenes: Especimen[], total: int|null, nombresTaxon: array<string, string>|null}
     */
    public function buscarPaginaConTotal(array $filtros): array
    {
        $items = $this->filtrarEnMemoria($filtros);
        $pagina = array_slice(
            $items,
            max(0, (int) ($filtros['offset'] ?? 0)),
            (int) ($filtros['limit'] ?? 200),
        );

        // `null` (y no `[]`) porque el doble NO resuelve nombres de taxón: no
        // conoce ese repositorio. Un mapa vacío significaría "ninguno de estos
        // especímenes tiene taxón", y el handler dejaría de resolverlos.
        return [
            'especimenes' => $pagina,
            'total' => $pagina === [] ? null : count($items),
            'nombresTaxon' => null,
        ];
    }

    /**
     * Aplica los filtros (sin limit/offset) y devuelve la lista ordenada de forma
     * determinista (por código de catálogo, luego id) para que la paginación sea
     * estable, igual que la rama Eloquent.
     *
     * @param  array<string, mixed>  $filtros
     * @return Especimen[]
     */
    private function filtrarEnMemoria(array $filtros): array
    {
        $items = array_values($this->store);

        $global = $filtros['busquedaGlobal'] ?? null;
        if ($global !== null && $global !== '') {
            $taxonIdsGlobal = array_flip($filtros['busquedaGlobalTaxonIds'] ?? []);
            $items = array_filter($items, function (Especimen $e) use ($global, $taxonIdsGlobal): bool {
                if ($e->taxonId() !== null && isset($taxonIdsGlobal[$e->taxonId()])) {
                    return true;
                }
                $campos = [
                    $e->codigoCatalogo(), $e->occurrenceId(), $e->catalogNumber(), $e->oldCode(),
                    $e->recordNumber(), $e->cardexLiquidCollectionCode(), $e->taxonVerbatim(),
                    $e->colector(), $e->localidad(), $e->localidadVerbatim(), $e->localityName(),
                    $e->municipality(), $e->stateProvince(),
                ];
                foreach ($campos as $valor) {
                    if ($valor !== null && stripos($valor, $global) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        if (! empty($filtros['taxonIds'])) {
            $set = array_flip($filtros['taxonIds']);
            $items = array_filter($items, fn (Especimen $e) => $e->taxonId() !== null && isset($set[$e->taxonId()]));
        }
        $idsNombre = $filtros['taxonNombreIds'] ?? [];
        $textoTaxon = $filtros['taxonNombreTexto'] ?? null;
        if (! empty($idsNombre) || ($textoTaxon !== null && $textoTaxon !== '')) {
            $setNombre = array_flip($idsNombre);
            $items = array_filter($items, function (Especimen $e) use ($setNombre, $idsNombre, $textoTaxon): bool {
                $porId = ! empty($idsNombre) && $e->taxonId() !== null && isset($setNombre[$e->taxonId()]);
                $porVerbatim = $textoTaxon !== null && $textoTaxon !== ''
                    && $e->taxonVerbatim() !== null
                    && stripos($e->taxonVerbatim(), $textoTaxon) !== false;

                return $porId || $porVerbatim;
            });
        }
        if (! empty($filtros['codigoCatalogo'])) {
            $items = array_filter($items, fn (Especimen $e) => stripos($e->codigoCatalogo(), $filtros['codigoCatalogo']) !== false);
        }
        if (! empty($filtros['occurrenceId'])) {
            $items = array_filter($items, fn (Especimen $e) => $e->occurrenceId() !== null && stripos($e->occurrenceId(), $filtros['occurrenceId']) !== false);
        }
        if (! empty($filtros['catalogNumber'])) {
            $items = array_filter($items, fn (Especimen $e) => $e->catalogNumber() !== null && stripos($e->catalogNumber(), $filtros['catalogNumber']) !== false);
        }
        if (! empty($filtros['localidad'])) {
            $items = array_filter($items, function (Especimen $e) use ($filtros): bool {
                foreach ([$e->localidad(), $e->localityName(), $e->stateProvince(), $e->municipality(), $e->country()] as $v) {
                    if ($v !== null && stripos($v, $filtros['localidad']) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }
        if (! empty($filtros['colector'])) {
            $items = array_filter($items, fn (Especimen $e) => stripos($e->colector(), $filtros['colector']) !== false);
        }
        if (! empty($filtros['fechaDesde'])) {
            $items = array_filter($items, fn (Especimen $e) => $e->fechaColecta() !== '' && $e->fechaColecta() >= $filtros['fechaDesde']);
        }
        if (! empty($filtros['fechaHasta'])) {
            $items = array_filter($items, fn (Especimen $e) => $e->fechaColecta() !== '' && $e->fechaColecta() <= $filtros['fechaHasta']);
        }
        if (! empty($filtros['estado'])) {
            $items = array_filter($items, fn (Especimen $e) => $e->estado()->value === $filtros['estado']);
        }
        if (! empty($filtros['estadoRevision'])) {
            $items = array_filter($items, fn (Especimen $e) => $e->estadoRevision()->value === $filtros['estadoRevision']);
        }
        if (! empty($filtros['motivoRevision'])) {
            $items = array_filter($items, fn (Especimen $e) => $e->motivoRevision() !== null && stripos($e->motivoRevision(), $filtros['motivoRevision']) !== false);
        }
        if (! empty($filtros['paraRevision'])) {
            $items = array_filter($items, fn (Especimen $e) => $e->estadoRevision()->value === 'pendiente' && $e->motivoRevision() !== null);
        }

        $items = array_values($items);

        // Mismo criterio que la rama Eloquent: orden pedido (si la clave está en
        // la lista blanca), con el código de catálogo como desempate estable.
        $clave = $filtros['ordenarPor'] ?? null;
        $ordenable = $clave !== null && in_array($clave, RegistroColumnasEspecimen::clavesOrdenables(), true);
        $desc = strtolower((string) ($filtros['ordenDireccion'] ?? 'asc')) === 'desc';

        usort($items, function (Especimen $a, Especimen $b) use ($ordenable, $clave, $desc): int {
            if ($ordenable) {
                $va = MapeadorFilaEspecimen::mapear($a)[$clave] ?? null;
                $vb = MapeadorFilaEspecimen::mapear($b)[$clave] ?? null;
                // NULLs al final en ambas direcciones, igual que `nulls last`.
                if ($va === null || $vb === null) {
                    if ($va !== $vb) {
                        return $va === null ? 1 : -1;
                    }
                } elseif ($va !== $vb) {
                    return $desc ? ($vb <=> $va) : ($va <=> $vb);
                }
            }

            return [$a->codigoCatalogo(), (string) $a->id()] <=> [$b->codigoCatalogo(), (string) $b->id()];
        });

        return $items;
    }

    /**
     * @param  string[]  $ids
     * @return array<string, string|null>
     */
    public function valoresDeCampoPorIds(array $ids, string $clave): array
    {
        $valores = [];
        foreach ($ids as $id) {
            $especimen = $this->store[$id] ?? null;
            if ($especimen !== null) {
                $valores[$id] = $especimen->valorDeCampoEditable($clave);
            }
        }

        return $valores;
    }

    /** @param string[] $ids */
    public function fijarCampoPorIds(array $ids, string $clave, ?string $valor): int
    {
        $afectados = 0;
        foreach ($ids as $id) {
            $especimen = $this->store[$id] ?? null;
            if ($especimen !== null) {
                $especimen->fijarCampoEditable($clave, $valor);
                $afectados++;
            }
        }

        return $afectados;
    }

    /** @param array<string, string|null> $valoresPorId */
    public function fijarCampoPorIdValor(array $valoresPorId, string $clave): int
    {
        $afectados = 0;
        foreach ($valoresPorId as $id => $valor) {
            $especimen = $this->store[$id] ?? null;
            if ($especimen !== null) {
                $especimen->fijarCampoEditable($clave, $valor);
                $afectados++;
            }
        }

        return $afectados;
    }

    /** @param int[] $filasOrigen
     *  @return int[] */
    public function filasOrigenExistentes(array $filasOrigen): array
    {
        $set = array_flip($filasOrigen);
        $existentes = [];
        foreach ($this->store as $especimen) {
            $fila = $especimen->filaOrigenExcel();
            if ($fila !== null && isset($set[$fila])) {
                $existentes[] = $fila;
            }
        }

        return $existentes;
    }

    /** @param string[] $codigos */
    public function marcarDevueltosPorCodigosCatalogo(array $codigos, \DateTimeImmutable $devueltoEn): int
    {
        $buscados = array_flip($codigos);
        $marcados = 0;
        foreach ($this->store as $especimen) {
            if (! isset($buscados[$especimen->codigoCatalogo()])) {
                continue;
            }
            if ($especimen->estadoCustodia()?->esDevolutivo() !== true) {
                continue;
            }
            $especimen->marcarComoDevuelto($devueltoEn);
            $marcados++;
        }

        return $marcados;
    }

    public function guardarBatch(array $especimenes): void
    {
        foreach ($especimenes as $especimen) {
            $this->guardar($especimen);
        }
    }

    /** @param string[] $codigos
     *  @return array{total: int, pendientesRevision: int} */
    public function resumenPorCodigosCatalogo(array $codigos): array
    {
        $buscados = array_flip($codigos);
        $total = 0;
        $pendientes = 0;
        foreach ($this->store as $especimen) {
            if (! isset($buscados[$especimen->codigoCatalogo()])) {
                continue;
            }
            $total++;
            if ($especimen->estadoRevision()->value === 'pendiente') {
                $pendientes++;
            }
        }

        return ['total' => $total, 'pendientesRevision' => $pendientes];
    }

    /** @param string[] $codigos
     *  @return string[] */
    public function codigosCatalogoExistentes(array $codigos): array
    {
        $buscados = array_flip($codigos);
        $existentes = [];
        foreach ($this->store as $especimen) {
            if (isset($buscados[$especimen->codigoCatalogo()])) {
                $existentes[] = $especimen->codigoCatalogo();
            }
        }

        return array_values(array_unique($existentes));
    }

    /** @return Especimen[] */
    public function buscarPorFechaVerbatimPendiente(string $verbatim, int $limit = 500): array
    {
        $out = [];
        foreach ($this->store as $e) {
            if ($e->fechaVerbatim() !== $verbatim) {
                continue;
            }
            if ($e->fechaColecta() !== '' && $e->fechaColecta() !== null) {
                continue;
            }
            $out[] = $e;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @return Especimen[] */
    public function buscarPorTaxonVerbatimPendiente(string $verbatim, int $limit = 500): array
    {
        $out = [];
        foreach ($this->store as $e) {
            if ($e->taxonId() !== null) {
                continue;
            }
            if ($e->taxonVerbatim() !== $verbatim) {
                continue;
            }
            $out[] = $e;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @return Especimen[] */
    public function buscarPorLocalidadVerbatimPendiente(string $verbatim, int $limit = 500): array
    {
        $out = [];
        foreach ($this->store as $e) {
            if ($e->localidadId() !== null) {
                continue;
            }
            if ($e->localidadVerbatim() !== $verbatim) {
                continue;
            }
            $out[] = $e;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @param  string[]  $ids */
    public function enlazarFechaPorIds(array $ids, string $fechaInicio, ?string $fechaFin = null): int
    {
        // La entidad no expone setter de fecha_colecta; en InMemory este método es
        // informativo (cuenta cuántos serían afectados). Eloquent hace el UPDATE real.
        $set = array_flip($ids);
        $contador = 0;
        foreach ($this->store as $e) {
            if (! isset($set[(string) $e->id()])) {
                continue;
            }
            if ($e->fechaColecta() !== '' && $e->fechaColecta() !== null) {
                continue;
            }
            $contador++;
        }

        return $contador;
    }

    /** @param  string[]  $ids */
    public function enlazarTaxonPorIds(array $ids, string $taxonId): int
    {
        $set = array_flip($ids);
        $taxon = TaxonId::desde($taxonId);
        $contador = 0;
        foreach ($this->store as $e) {
            if (! isset($set[(string) $e->id()])) {
                continue;
            }
            if ($e->taxonId() !== null) {
                continue;
            }
            $e->enlazarTaxon($taxon);
            $contador++;
        }

        return $contador;
    }

    /** @param  string[]  $ids */
    public function enlazarLocalidadPorIds(array $ids, string $localidadId): int
    {
        $set = array_flip($ids);
        $localidad = LocalidadId::desde($localidadId);
        $contador = 0;
        foreach ($this->store as $e) {
            if (! isset($set[(string) $e->id()])) {
                continue;
            }
            if ($e->localidadId() !== null) {
                continue;
            }
            $e->enlazarLocalidad($localidad);
            $contador++;
        }

        return $contador;
    }

    public function contarSinCoordenadas(): int
    {
        return count($this->sinCoordenadas());
    }

    /** @return Especimen[] */
    public function buscarSinCoordenadas(int $limit, int $offset): array
    {
        return array_slice($this->sinCoordenadas(), max(0, $offset), max(1, $limit));
    }

    /** @return Especimen[] */
    private function sinCoordenadas(): array
    {
        return array_values(array_filter(
            $this->store,
            fn ($e) => $e->decimalLatitude() === null || $e->decimalLongitude() === null,
        ));
    }
}
