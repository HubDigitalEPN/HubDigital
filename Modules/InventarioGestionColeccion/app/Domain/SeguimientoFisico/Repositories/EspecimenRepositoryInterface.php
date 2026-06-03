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
     * Permite la idempotencia del importador: si un espécimen ya fue creado
     * a partir de una fila específica del Excel, no se duplica al re-correr.
     */
    public function existePorFilaOrigen(int $filaOrigenExcel): bool;

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
     * Devuelve los `fila_origen_excel` ya persistidos cuyo valor está dentro
     * del set proporcionado. Permite chequear idempotencia en bulk antes de
     * abrir el chunk de inserts.
     *
     * @param  int[]  $filasOrigen
     * @return int[]
     */
    public function filasOrigenExistentes(array $filasOrigen): array;
}
