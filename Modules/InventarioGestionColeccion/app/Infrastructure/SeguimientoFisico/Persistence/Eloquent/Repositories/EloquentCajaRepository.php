<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\CajaEloquentModel;

/**
 * Implementación Eloquent del repositorio de cajas: traduce entre la entidad Caja y su modelo
 * persistido, incluyendo el aplanado de la clasificación taxonómica a JSON y su rehidratación.
 * Resuelve las búsquedas clave del componente (por id, por código y por código RFID).
 */
class EloquentCajaRepository implements CajaRepository
{
    /** Genera un nuevo identificador de caja antes de persistirla. */
    public function nextIdentity(): CajaId
    {
        return CajaId::generar();
    }

    /** Inserta o actualiza la caja según su id, serializando su clasificación taxonómica. */
    public function guardar(Caja $caja): void
    {
        CajaEloquentModel::updateOrCreate(
            ['id' => (string) $caja->id()],
            [
                'codigo' => (string) $caja->codigo(),
                'es_especial' => $caja->esEspecial(),
                'observacion' => $caja->observacion(),
                'clasificacion_taxonomica' => $this->clasificacionToArray($caja->clasificacionTaxonomica()),
                'nombre' => $caja->nombre(),
                'estado' => $caja->estadoActual()->valor(),
                'ranura_actual_id' => $caja->ranuraActualId() ? (string) $caja->ranuraActualId() : null,
                'codigo_rfid' => $caja->codigoRfid() ? (string) $caja->codigoRfid() : null,
            ]
        );
    }

    /** Recupera una caja por su identificador. */
    public function buscarPorId(CajaId $id): ?Caja
    {
        $model = CajaEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /** Recupera una caja por su código legible (etiqueta del curador). */
    public function buscarPorCodigo(CodigoCaja $codigo): ?Caja
    {
        $model = CajaEloquentModel::where('codigo', (string) $codigo)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** Recupera una caja por su código RFID, usado al procesar un evento del ESP32. */
    public function buscarPorCodigoRfid(CodigoRfid $rfid): ?Caja
    {
        $model = CajaEloquentModel::where('codigo_rfid', (string) $rfid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Lista todas las cajas registradas.
     *
     * @return Caja[]
     */
    public function buscarTodas(): array
    {
        return CajaEloquentModel::all()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** Elimina la caja indicada de la persistencia. */
    public function eliminar(CajaId $id): void
    {
        CajaEloquentModel::destroy((string) $id);
    }

    /** Reconstituye la entidad Caja a partir de la fila persistida. */
    private function toDomain(CajaEloquentModel $model): Caja
    {
        return Caja::reconstituir(
            id: CajaId::desde($model->id),
            codigo: CodigoCaja::desde($model->codigo),
            estado: EstadoCaja::from($model->estado),
            ranuraActualId: $model->ranura_actual_id ? RanuraId::desde($model->ranura_actual_id) : null,
            codigoRfid: $model->codigo_rfid ? CodigoRfid::desde($model->codigo_rfid) : null,
            esEspecial: (bool) $model->es_especial,
            observacion: $model->observacion,
            nombre: $model->nombre,
            clasificacionTaxonomica: $this->arrayToClasificacion($model->clasificacion_taxonomica),
        );
    }

    /** Aplana la clasificación taxonómica a un array asociativo para guardarla como JSON, o null si está vacía. */
    private function clasificacionToArray(?ClasificacionTaxonomica $clasificacion): ?array
    {
        if ($clasificacion === null || $clasificacion->estaVacia()) {
            return null;
        }

        return [
            'orden' => $clasificacion->orden(),
            'suborden' => $clasificacion->suborden(),
            'superfamilia' => $clasificacion->superfamilia(),
            'familia' => $clasificacion->familia(),
            'subfamilia' => $clasificacion->subfamilia(),
            'genero' => $clasificacion->genero(),
            'especie' => $clasificacion->especie(),
        ];
    }

    /** Rehidrata la clasificación taxonómica desde el array persistido, o null si no hay datos. */
    private function arrayToClasificacion(?array $data): ?ClasificacionTaxonomica
    {
        if ($data === null) {
            return null;
        }

        $clasificacion = ClasificacionTaxonomica::desde(
            orden: $data['orden'] ?? null,
            suborden: $data['suborden'] ?? null,
            superfamilia: $data['superfamilia'] ?? null,
            familia: $data['familia'] ?? null,
            subfamilia: $data['subfamilia'] ?? null,
            genero: $data['genero'] ?? null,
            especie: $data['especie'] ?? null,
        );

        return $clasificacion->estaVacia() ? null : $clasificacion;
    }
}
