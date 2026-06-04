<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
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

    public function existePorFilaOrigen(int $filaOrigenExcel): bool
    {
        foreach ($this->store as $especimen) {
            if ($especimen->filaOrigenExcel() === $filaOrigenExcel) {
                return true;
            }
        }

        return false;
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

    public function guardarBatch(array $especimenes): void
    {
        foreach ($especimenes as $especimen) {
            $this->guardar($especimen);
        }
    }
}
