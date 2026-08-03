<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DetalleEdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EdicionMasiva;

interface BitacoraEdicionMasivaRepositoryInterface
{
    public function nextIdentity(): string;

    public function guardar(EdicionMasiva $edicion): void;

    /**
     * Inserta los detalles de una edición. Se llama una sola vez por operación,
     * con el lote completo.
     *
     * @param  DetalleEdicionMasiva[]  $detalles
     */
    public function guardarDetalles(array $detalles): void;

    public function buscarPorId(string $id): ?EdicionMasiva;

    /** @return DetalleEdicionMasiva[] */
    public function detallesDe(string $edicionId): array;

    /**
     * Ediciones más recientes primero: el historial se lee de arriba abajo y lo
     * último que hizo el curador es lo que más probablemente quiera deshacer.
     *
     * @return EdicionMasiva[]
     */
    public function listarRecientes(int $limite = 20): array;

    /** Persiste el resultado de una reversión fila a fila. */
    public function actualizarEstadoDetalles(array $detalles): void;
}
