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

final class EloquentPrestamoRepository implements PrestamoRepositoryInterface
{
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

    public function buscarPorId(PrestamoId $id): ?Prestamo
    {
        $model = PrestamoEloquentModel::find((string) $id);

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function nextIdentity(): PrestamoId
    {
        return PrestamoId::generate();
    }

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
