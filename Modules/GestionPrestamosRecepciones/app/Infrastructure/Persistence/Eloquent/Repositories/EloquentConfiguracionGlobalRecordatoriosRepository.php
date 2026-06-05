<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ConfiguracionGlobalRecordatoriosId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ConfiguracionGlobalRecordatoriosEloquentModel;

final class EloquentConfiguracionGlobalRecordatoriosRepository implements ConfiguracionGlobalRecordatoriosRepositoryInterface
{
    public function guardar(ConfiguracionGlobalRecordatorios $configuracion): void
    {
        ConfiguracionGlobalRecordatoriosEloquentModel::updateOrCreate(
            ['id' => (string) $configuracion->id()],
            [
                'curador_id' => $configuracion->curadorId(),
                'dias_antes' => $configuracion->diasAntes(),
            ],
        );
    }

    public function obtenerUnica(): ?ConfiguracionGlobalRecordatorios
    {
        $model = ConfiguracionGlobalRecordatoriosEloquentModel::first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function nextIdentity(): ConfiguracionGlobalRecordatoriosId
    {
        return ConfiguracionGlobalRecordatoriosId::generate();
    }

    private function toDomain(ConfiguracionGlobalRecordatoriosEloquentModel $model): ConfiguracionGlobalRecordatorios
    {
        return ConfiguracionGlobalRecordatorios::reconstituir(
            id: ConfiguracionGlobalRecordatoriosId::fromString($model->id),
            curadorId: $model->curador_id,
            diasAntes: $model->dias_antes,
        );
    }
}
