<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

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
