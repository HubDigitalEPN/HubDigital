<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecordatorioDevolucionId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\RecordatorioDevolucionEloquentModel;

final class EloquentRecordatorioDevolucionRepository implements RecordatorioDevolucionRepositoryInterface
{
    public function guardar(RecordatorioDevolucion $recordatorio): void
    {
        RecordatorioDevolucionEloquentModel::updateOrCreate(
            ['id' => (string) $recordatorio->id()],
            [
                'prestamo_id' => (string) $recordatorio->prestamoId(),
                'dias_antes_vencimiento' => $recordatorio->diasAntesVencimiento(),
                'fecha_programada' => $recordatorio->fechaProgramada()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function guardarTodos(array $recordatorios): void
    {
        foreach ($recordatorios as $recordatorio) {
            $this->guardar($recordatorio);
        }
    }

    public function listarPorPrestamo(PrestamoId $prestamoId): array
    {
        return RecordatorioDevolucionEloquentModel::where('prestamo_id', (string) $prestamoId)
            ->get()
            ->map(fn (RecordatorioDevolucionEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    public function eliminarPorPrestamo(PrestamoId $prestamoId): void
    {
        RecordatorioDevolucionEloquentModel::where('prestamo_id', (string) $prestamoId)->delete();
    }

    public function nextIdentity(): RecordatorioDevolucionId
    {
        return RecordatorioDevolucionId::generate();
    }

    private function toDomain(RecordatorioDevolucionEloquentModel $model): RecordatorioDevolucion
    {
        return RecordatorioDevolucion::reconstituir(
            id: RecordatorioDevolucionId::fromString($model->id),
            prestamoId: PrestamoId::fromString($model->prestamo_id),
            fechaProgramada: DateTimeImmutable::createFromInterface($model->fecha_programada),
            diasAntesVencimiento: $model->dias_antes_vencimiento,
        );
    }
}
