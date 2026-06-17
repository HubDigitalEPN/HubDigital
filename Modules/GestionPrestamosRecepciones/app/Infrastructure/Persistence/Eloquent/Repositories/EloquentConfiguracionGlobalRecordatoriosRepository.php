<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ConfiguracionGlobalRecordatoriosId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ConfiguracionGlobalRecordatoriosEloquentModel;

/**
 * Implementación Eloquent del repositorio de configuración global de recordatorios.
 *
 * Maneja la persistencia de la configuración única que define cuántos días antes
 * del vencimiento se deben enviar las notificaciones.
 */
final class EloquentConfiguracionGlobalRecordatoriosRepository implements ConfiguracionGlobalRecordatoriosRepositoryInterface
{
    /**
     * Persiste o actualiza la configuración global.
     */
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

    /**
     * Obtiene la única instancia de configuración existente.
     */
    public function obtenerUnica(): ?ConfiguracionGlobalRecordatorios
    {
        $model = ConfiguracionGlobalRecordatoriosEloquentModel::first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Genera un nuevo identificador para la configuración.
     */
    public function nextIdentity(): ConfiguracionGlobalRecordatoriosId
    {
        return ConfiguracionGlobalRecordatoriosId::generate();
    }

    /**
     * Convierte el modelo Eloquent a la entidad de dominio.
     */
    private function toDomain(ConfiguracionGlobalRecordatoriosEloquentModel $model): ConfiguracionGlobalRecordatorios
    {
        return ConfiguracionGlobalRecordatorios::reconstituir(
            id: ConfiguracionGlobalRecordatoriosId::fromString($model->id),
            curadorId: $model->curador_id,
            diasAntes: $model->dias_antes,
        );
    }
}
