<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DetalleEdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\BitacoraEdicionMasivaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoReversionDetalle;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEdicionMasiva;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\DetalleEdicionMasivaEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EdicionMasivaEloquentModel;

class EloquentBitacoraEdicionMasivaRepository implements BitacoraEdicionMasivaRepositoryInterface
{
    public function nextIdentity(): string
    {
        return (string) Str::uuid();
    }

    public function guardar(EdicionMasiva $edicion): void
    {
        EdicionMasivaEloquentModel::updateOrCreate(
            ['id' => $edicion->id()],
            [
                'tipo' => $edicion->tipo()->value,
                'campo' => $edicion->campo(),
                'valor_aplicado' => $edicion->valorAplicado(),
                'texto_buscado' => $edicion->textoBuscado(),
                'texto_reemplazo' => $edicion->textoReemplazo(),
                'total_afectados' => $edicion->totalAfectados(),
                'actor_id' => $edicion->actorId(),
                'actor_nombre' => $edicion->actorNombre(),
                'deshecha_en' => $edicion->deshechaEn(),
            ],
        );
    }

    /** @param DetalleEdicionMasiva[] $detalles */
    public function guardarDetalles(array $detalles): void
    {
        if ($detalles === []) {
            return;
        }

        // Un solo INSERT para todo el lote: con selecciones de cientos de filas,
        // insertar de una en una multiplicaría los viajes a la base dentro de la
        // transacción.
        DetalleEdicionMasivaEloquentModel::insert(array_map(
            fn (DetalleEdicionMasiva $d) => [
                'id' => $d->id(),
                'edicion_id' => $d->edicionId(),
                'especimen_id' => $d->especimenId(),
                'valor_previo' => $d->valorPrevio(),
                'valor_aplicado' => $d->valorAplicado(),
                'estado_reversion' => $d->estadoReversion()->value,
            ],
            $detalles,
        ));
    }

    public function buscarPorId(string $id): ?EdicionMasiva
    {
        $modelo = EdicionMasivaEloquentModel::find($id);

        return $modelo !== null ? $this->aDominio($modelo) : null;
    }

    /** @return DetalleEdicionMasiva[] */
    public function detallesDe(string $edicionId): array
    {
        return DetalleEdicionMasivaEloquentModel::where('edicion_id', $edicionId)
            ->get()
            ->map(fn ($m) => DetalleEdicionMasiva::reconstituir(
                id: (string) $m->id,
                edicionId: (string) $m->edicion_id,
                especimenId: (string) $m->especimen_id,
                valorPrevio: $m->valor_previo !== null ? (string) $m->valor_previo : null,
                valorAplicado: $m->valor_aplicado !== null ? (string) $m->valor_aplicado : null,
                estadoReversion: EstadoReversionDetalle::from((string) $m->estado_reversion),
            ))
            ->all();
    }

    /** @return EdicionMasiva[] */
    public function listarRecientes(int $limite = 20): array
    {
        return EdicionMasivaEloquentModel::orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->map(fn ($m) => $this->aDominio($m))
            ->all();
    }

    /** @param DetalleEdicionMasiva[] $detalles */
    public function actualizarEstadoDetalles(array $detalles): void
    {
        $ahora = now();

        // Se agrupan por estado para hacer un UPDATE por estado en vez de uno
        // por fila: normalmente son dos sentencias (revertidos y conflictos).
        $porEstado = [];
        foreach ($detalles as $detalle) {
            $porEstado[$detalle->estadoReversion()->value][] = $detalle->id();
        }

        foreach ($porEstado as $estado => $ids) {
            DetalleEdicionMasivaEloquentModel::whereIn('id', $ids)->update([
                'estado_reversion' => $estado,
                'revertido_en' => $estado === EstadoReversionDetalle::Pendiente->value ? null : $ahora,
            ]);
        }
    }

    private function aDominio(EdicionMasivaEloquentModel $m): EdicionMasiva
    {
        return EdicionMasiva::reconstituir(
            id: (string) $m->id,
            tipo: TipoEdicionMasiva::from((string) $m->tipo),
            campo: (string) $m->campo,
            valorAplicado: $m->valor_aplicado !== null ? (string) $m->valor_aplicado : null,
            textoBuscado: $m->texto_buscado !== null ? (string) $m->texto_buscado : null,
            textoReemplazo: $m->texto_reemplazo !== null ? (string) $m->texto_reemplazo : null,
            totalAfectados: (int) $m->total_afectados,
            actorId: $m->actor_id !== null ? (string) $m->actor_id : null,
            actorNombre: $m->actor_nombre !== null ? (string) $m->actor_nombre : null,
            creadoEn: new DateTimeImmutable((string) $m->created_at),
            deshechaEn: $m->deshecha_en !== null ? new DateTimeImmutable((string) $m->deshecha_en) : null,
        );
    }
}
