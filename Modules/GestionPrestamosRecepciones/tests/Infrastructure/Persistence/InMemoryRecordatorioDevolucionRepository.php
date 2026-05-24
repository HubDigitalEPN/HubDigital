<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecordatorioDevolucionId;

final class InMemoryRecordatorioDevolucionRepository implements RecordatorioDevolucionRepositoryInterface
{
    /** @var array<string, RecordatorioDevolucion> */
    private array $store = [];

    public function guardar(RecordatorioDevolucion $recordatorio): void
    {
        $this->store[(string) $recordatorio->id()] = $recordatorio;
    }

    /** @param list<RecordatorioDevolucion> $recordatorios */
    public function guardarTodos(array $recordatorios): void
    {
        foreach ($recordatorios as $recordatorio) {
            $this->guardar($recordatorio);
        }
    }

    /** @return list<RecordatorioDevolucion> */
    public function listarPorPrestamo(PrestamoId $prestamoId): array
    {
        return array_values(
            array_filter(
                $this->store,
                fn (RecordatorioDevolucion $r) => (string) $r->prestamoId() === (string) $prestamoId,
            )
        );
    }

    public function eliminarPorPrestamo(PrestamoId $prestamoId): void
    {
        $this->store = array_filter(
            $this->store,
            fn (RecordatorioDevolucion $r) => (string) $r->prestamoId() !== (string) $prestamoId,
        );
    }

    public function nextIdentity(): RecordatorioDevolucionId
    {
        return RecordatorioDevolucionId::generate();
    }
}
