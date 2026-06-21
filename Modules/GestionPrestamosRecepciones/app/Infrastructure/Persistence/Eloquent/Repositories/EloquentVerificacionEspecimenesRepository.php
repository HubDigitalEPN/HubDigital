<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEspecimenes;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEspecimenesId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\VerificacionEspecimenesEloquentModel;

/**
 * Implementación Eloquent del repositorio de verificaciones de especímenes.
 *
 * Persiste y recupera el agregado {@see VerificacionEspecimenes} para los distintos
 * momentos de verificación (recepción / devolución) de un préstamo.
 */
final class EloquentVerificacionEspecimenesRepository implements VerificacionEspecimenesRepositoryInterface
{
    public function guardar(VerificacionEspecimenes $verificacion): void
    {
        $observaciones = array_map(
            fn (ObservacionEspecimen $obs): array => [
                'item_prestamo_id' => $obs->itemPrestamoId,
                'descripcion' => $obs->descripcion,
            ],
            $verificacion->observaciones(),
        );

        VerificacionEspecimenesEloquentModel::updateOrCreate(
            ['id' => (string) $verificacion->id()],
            [
                'prestamo_id' => (string) $verificacion->prestamoId(),
                'tipo' => $verificacion->tipo()->value,
                'resultado' => $verificacion->resultado()->value,
                'observaciones' => $observaciones,
                'observacion_general' => $verificacion->observacionGeneral(),
            ],
        );
    }

    public function buscarPorPrestamoYTipo(PrestamoId $prestamoId, TipoVerificacion $tipo): ?VerificacionEspecimenes
    {
        $model = VerificacionEspecimenesEloquentModel::query()
            ->where('prestamo_id', (string) $prestamoId)
            ->where('tipo', $tipo->value)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function nextIdentity(): VerificacionEspecimenesId
    {
        return VerificacionEspecimenesId::generate();
    }

    /**
     * Mapea el modelo Eloquent a la entidad de dominio.
     */
    private function toDomain(VerificacionEspecimenesEloquentModel $model): VerificacionEspecimenes
    {
        $observaciones = array_map(
            fn (array $obs): ObservacionEspecimen => new ObservacionEspecimen(
                itemPrestamoId: $obs['item_prestamo_id'],
                descripcion: $obs['descripcion'],
            ),
            $model->observaciones ?? [],
        );

        return VerificacionEspecimenes::reconstituir(
            id: VerificacionEspecimenesId::fromString($model->id),
            prestamoId: PrestamoId::fromString($model->prestamo_id),
            tipo: TipoVerificacion::from($model->tipo),
            resultado: ResultadoVerificacion::from($model->resultado),
            observaciones: $observaciones,
            observacionGeneral: $model->observacion_general,
        );
    }
}
