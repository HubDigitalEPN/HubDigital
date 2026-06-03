<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\MuestraColectaEloquentModel;

class EloquentMuestraColectaRepository implements MuestraColectaRepositoryInterface
{
    public function nextIdentity(): MuestraColectaId
    {
        return MuestraColectaId::generar();
    }

    public function guardar(MuestraColecta $muestra): void
    {
        MuestraColectaEloquentModel::updateOrCreate(
            ['id' => (string) $muestra->id()],
            [
                'codigo_muestra' => $muestra->codigoMuestra(),
                'fecha_colecta' => $muestra->fechaColecta()?->format('Y-m-d'),
                'fecha_verbatim' => $muestra->fechaVerbatim(),
                'localidad_id' => $muestra->localidadId() !== null ? (string) $muestra->localidadId() : null,
                'localidad_verbatim' => $muestra->localidadVerbatim(),
                'colector' => $muestra->colector(),
                'sampling_protocol' => $muestra->samplingProtocol(),
                'estado_revision' => $muestra->estadoRevision()->value,
                'motivo_revision' => $muestra->motivoRevision(),
            ]
        );
    }

    public function buscarPorId(MuestraColectaId $id): ?MuestraColecta
    {
        $model = MuestraColectaEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorCodigoMuestra(string $codigo): ?MuestraColecta
    {
        $model = MuestraColectaEloquentModel::where('codigo_muestra', $codigo)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** @return MuestraColecta[] */
    public function buscarPorEstado(EstadoRevision $estado): array
    {
        return MuestraColectaEloquentModel::where('estado_revision', $estado->value)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return MuestraColecta[] */
    public function buscarTodas(): array
    {
        return MuestraColectaEloquentModel::orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function contarTodas(): int
    {
        return MuestraColectaEloquentModel::count();
    }

    public function guardarBatch(array $muestras): void
    {
        if ($muestras === []) {
            return;
        }

        $ahora = now();
        $rows = [];
        foreach ($muestras as $muestra) {
            $rows[] = [
                'id' => (string) $muestra->id(),
                'codigo_muestra' => $muestra->codigoMuestra(),
                'fecha_colecta' => $muestra->fechaColecta()?->format('Y-m-d'),
                'fecha_verbatim' => $muestra->fechaVerbatim(),
                'localidad_id' => $muestra->localidadId() !== null ? (string) $muestra->localidadId() : null,
                'localidad_verbatim' => $muestra->localidadVerbatim(),
                'colector' => $muestra->colector(),
                'sampling_protocol' => $muestra->samplingProtocol(),
                'estado_revision' => $muestra->estadoRevision()->value,
                'motivo_revision' => $muestra->motivoRevision(),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::table('taxonomia.muestras_colecta')->insert($rows);
    }

    private function toDomain(MuestraColectaEloquentModel $model): MuestraColecta
    {
        $fechaColecta = null;
        if ($model->fecha_colecta !== null) {
            $fechaColecta = $model->fecha_colecta instanceof \DateTimeImmutable
                ? $model->fecha_colecta
                : new \DateTimeImmutable((string) $model->fecha_colecta);
        }

        return MuestraColecta::reconstituir(
            id: MuestraColectaId::desde($model->id),
            codigoMuestra: $model->codigo_muestra,
            fechaColecta: $fechaColecta,
            fechaVerbatim: $model->fecha_verbatim,
            localidadId: $model->localidad_id !== null ? LocalidadId::desde($model->localidad_id) : null,
            localidadVerbatim: $model->localidad_verbatim,
            colector: $model->colector,
            samplingProtocol: $model->sampling_protocol,
            estadoRevision: EstadoRevision::from($model->estado_revision),
            motivoRevision: $model->motivo_revision,
        );
    }
}
