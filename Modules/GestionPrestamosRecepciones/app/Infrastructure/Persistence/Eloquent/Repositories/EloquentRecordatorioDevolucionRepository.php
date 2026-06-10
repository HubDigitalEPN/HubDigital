<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecordatorioDevolucionId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\RecordatorioDevolucionEloquentModel;

/**
 * Implementación Eloquent del repositorio de recordatorios de devolución.
 *
 * Gestiona la programación y persistencia de las notificaciones de vencimiento por préstamo.
 */
final class EloquentRecordatorioDevolucionRepository implements RecordatorioDevolucionRepositoryInterface
{
    /**
     * Persiste un recordatorio de devolución.
     */
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

    /**
     * Persiste una lista de recordatorios.
     *
     * @param list<RecordatorioDevolucion> $recordatorios
     */
    public function guardarTodos(array $recordatorios): void
    {
        foreach ($recordatorios as $recordatorio) {
            $this->guardar($recordatorio);
        }
    }

    /**
     * Lista todos los recordatorios programados para un préstamo específico.
     *
     * @return list<RecordatorioDevolucion>
     */
    public function listarPorPrestamo(PrestamoId $prestamoId): array
    {
        return RecordatorioDevolucionEloquentModel::where('prestamo_id', (string) $prestamoId)
            ->get()
            ->map(fn (RecordatorioDevolucionEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    /**
     * Elimina todos los recordatorios asociados a un préstamo.
     */
    public function eliminarPorPrestamo(PrestamoId $prestamoId): void
    {
        RecordatorioDevolucionEloquentModel::where('prestamo_id', (string) $prestamoId)->delete();
    }

    /**
     * Genera un nuevo identificador para un recordatorio.
     */
    public function nextIdentity(): RecordatorioDevolucionId
    {
        return RecordatorioDevolucionId::generate();
    }

    /**
     * Convierte el modelo Eloquent a la entidad de dominio.
     */
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
