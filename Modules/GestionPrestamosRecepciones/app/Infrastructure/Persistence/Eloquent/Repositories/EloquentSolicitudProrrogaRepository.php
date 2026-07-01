<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudProrrogaRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudProrrogaId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudProrrogaEloquentModel;

/**
 * Implementación Eloquent del repositorio de solicitudes de prórroga.
 *
 * Maneja la persistencia del agregado {@see SolicitudProrroga} en la base de datos.
 */
final class EloquentSolicitudProrrogaRepository implements SolicitudProrrogaRepositoryInterface
{
    /**
     * Persiste o actualiza una solicitud de prórroga.
     */
    public function guardar(SolicitudProrroga $solicitud): void
    {
        SolicitudProrrogaEloquentModel::updateOrCreate(
            ['id' => (string) $solicitud->id()],
            [
                'prestamo_id' => (string) $solicitud->prestamoId(),
                'fecha_propuesta' => $solicitud->fechaPropuesta()->format('Y-m-d H:i:s'),
                'justificacion' => $solicitud->justificacion(),
                'estado' => $solicitud->estado()->value,
                'solicitada_en' => $solicitud->solicitadaEn()->format('Y-m-d H:i:s'),
                'comentario_resolucion' => $solicitud->comentarioResolucion(),
                'resuelta_en' => $solicitud->resueltaEn()?->format('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * Busca una solicitud de prórroga por su identificador.
     */
    public function buscarPorId(SolicitudProrrogaId $id): ?SolicitudProrroga
    {
        $model = SolicitudProrrogaEloquentModel::find((string) $id);

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Busca la solicitud de prórroga pendiente (en estado Solicitada) de un préstamo.
     */
    public function buscarPendientePorPrestamo(PrestamoId $prestamoId): ?SolicitudProrroga
    {
        $model = SolicitudProrrogaEloquentModel::where('prestamo_id', (string) $prestamoId)
            ->where('estado', EstadoSolicitudProrroga::Solicitada->value)
            ->orderByDesc('solicitada_en')
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Genera un nuevo identificador único para una solicitud de prórroga.
     */
    public function nextIdentity(): SolicitudProrrogaId
    {
        return SolicitudProrrogaId::generate();
    }

    /**
     * Convierte el modelo Eloquent a la entidad de dominio SolicitudProrroga.
     */
    private function toDomain(SolicitudProrrogaEloquentModel $model): SolicitudProrroga
    {
        return SolicitudProrroga::reconstituir(
            id: SolicitudProrrogaId::fromString($model->id),
            prestamoId: PrestamoId::fromString($model->prestamo_id),
            fechaPropuesta: DateTimeImmutable::createFromInterface($model->fecha_propuesta),
            justificacion: $model->justificacion,
            estado: EstadoSolicitudProrroga::from($model->estado),
            solicitadaEn: DateTimeImmutable::createFromInterface($model->solicitada_en),
            comentarioResolucion: $model->comentario_resolucion,
            resueltaEn: $model->resuelta_en !== null
                ? DateTimeImmutable::createFromInterface($model->resuelta_en)
                : null,
        );
    }
}
