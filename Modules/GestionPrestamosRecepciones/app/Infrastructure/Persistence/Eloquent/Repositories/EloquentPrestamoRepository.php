<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

/**
 * Implementación Eloquent del repositorio de préstamos.
 *
 * Maneja la persistencia del agregado {@see Prestamo} en la base de datos.
 */
final class EloquentPrestamoRepository implements PrestamoRepositoryInterface
{
    /**
     * Persiste o actualiza un préstamo.
     */
    public function guardar(Prestamo $prestamo): void
    {
        PrestamoEloquentModel::updateOrCreate(
            ['id' => (string) $prestamo->id()],
            [
                'acta_prestamo_id' => (string) $prestamo->actaPrestamoId(),
                'investigador_id' => $prestamo->investigadorId(),
                'estado' => $prestamo->estado()->value,
                'iniciado_en' => $prestamo->iniciadoEn()->format('Y-m-d H:i:s'),
                'fecha_fin' => $prestamo->fechaFin()->format('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * Busca un préstamo por su identificador.
     */
    public function buscarPorId(PrestamoId $id): ?Prestamo
    {
        $model = PrestamoEloquentModel::find((string) $id);

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Busca un préstamo por el identificador del acta asociada.
     */
    public function buscarPorActaId(ActaPrestamoId $actaId): ?Prestamo
    {
        $model = PrestamoEloquentModel::where('acta_prestamo_id', (string) $actaId)->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Lista todos los préstamos que se encuentran en estado Activo.
     *
     * @return list<Prestamo>
     */
    public function listarActivos(): array
    {
        return PrestamoEloquentModel::where('estado', EstadoPrestamo::Activo->value)
            ->get()
            ->map(fn (PrestamoEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    /**
     * Genera un nuevo identificador único para un préstamo.
     */
    public function nextIdentity(): PrestamoId
    {
        return PrestamoId::generate();
    }

    /**
     * Convierte el modelo Eloquent a la entidad de dominio Prestamo.
     */
    private function toDomain(PrestamoEloquentModel $model): Prestamo
    {
        return Prestamo::reconstituir(
            id: PrestamoId::fromString($model->id),
            actaPrestamoId: ActaPrestamoId::fromString($model->acta_prestamo_id),
            investigadorId: $model->investigador_id,
            estado: EstadoPrestamo::from($model->estado),
            iniciadoEn: DateTimeImmutable::createFromInterface($model->iniciado_en),
            fechaFin: DateTimeImmutable::createFromInterface($model->fecha_fin),
        );
    }
}
