<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecordatorioDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecordatorioDevolucionId;

interface RecordatorioDevolucionRepositoryInterface
{
    public function guardar(RecordatorioDevolucion $recordatorio): void;

    /**
     * @param  list<RecordatorioDevolucion>  $recordatorios
     */
    public function guardarTodos(array $recordatorios): void;

    /**
     * @return list<RecordatorioDevolucion>
     */
    public function listarPorPrestamo(PrestamoId $prestamoId): array;

    public function eliminarPorPrestamo(PrestamoId $prestamoId): void;

    public function nextIdentity(): RecordatorioDevolucionId;
}
