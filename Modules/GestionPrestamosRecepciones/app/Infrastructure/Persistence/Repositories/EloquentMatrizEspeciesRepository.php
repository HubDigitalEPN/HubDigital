<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Entities\RegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RegistroEspecimenId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RegistroEspecimenEloquentModel;

/**
 * Implementación Eloquent del repositorio de matrices de especies.
 *
 * Persiste y recupera el agregado {@see MatrizEspecies} y sus registros de especímenes individuales.
 */
final class EloquentMatrizEspeciesRepository implements MatrizEspeciesRepositoryInterface
{
    /**
     * Genera un nuevo identificador único para una matriz.
     */
    public function nextIdentity(): MatrizEspeciesId
    {
        return MatrizEspeciesId::generate();
    }

    /**
     * Persiste la matriz de especies y sus registros en la base de datos.
     */
    public function guardar(MatrizEspecies $matriz): void
    {
        $now = now()->toDateTimeString();

        MatrizEspeciesEloquentModel::upsert(
            [[
                'id' => (string) $matriz->id(),
                'solicitud_id' => $matriz->solicitudId(),
                'tipo_tramite' => $matriz->tipoTramite(),
                'estado' => $matriz->estado()->value,
                'campos_dwc_presentes' => json_encode($matriz->camposDwCPresentes()),
                'identificacion_original_conservada' => $matriz->identificacionOriginalConservada(),
                'updated_at' => $now,
                'created_at' => $now,
            ]],
            ['id'],
            ['solicitud_id', 'tipo_tramite', 'estado', 'campos_dwc_presentes', 'identificacion_original_conservada', 'updated_at'],
        );

        $rows = [];
        $idsActuales = [];

        foreach ($matriz->registros() as $registroId => $registro) {
            $rows[] = [
                'id' => $registroId,
                'matriz_id' => (string) $matriz->id(),
                'nombre_cientifico' => $registro->nombreCientifico(),
                'nombre_corregido' => $registro->nombreCorregido(),
                'estado' => $registro->estado()->value,
                'no_catalogado' => $registro->esNoCatalogado(),
                'motivo_justificacion' => $registro->motivoJustificacion(),
                'datos_dwc' => json_encode($registro->datosDwC()) ?: null,
                'normalizaciones' => json_encode($registro->normalizaciones()) ?: null,
                'updated_at' => $now,
                'created_at' => $now,
            ];
            $idsActuales[] = $registroId;
        }

        if ($rows !== []) {
            RegistroEspecimenEloquentModel::upsert(
                $rows,
                ['id'],
                ['nombre_cientifico', 'nombre_corregido', 'estado', 'no_catalogado', 'motivo_justificacion', 'datos_dwc', 'normalizaciones', 'updated_at'],
            );
        }

        RegistroEspecimenEloquentModel::where('matriz_id', (string) $matriz->id())
            ->whereNotIn('id', $idsActuales)
            ->delete();
    }

    /**
     * Busca una matriz de especies por su identificador único.
     */
    public function buscarPorId(MatrizEspeciesId $matrizId): ?MatrizEspecies
    {
        $model = MatrizEspeciesEloquentModel::with('registros')->find((string) $matrizId);

        if ($model === null) {
            return null;
        }

        return $this->reconstituir($model);
    }

    /**
     * Busca la matriz de especies asociada a una solicitud (préstamo o depósito).
     */
    public function buscarPorSolicitudId(string $solicitudId): ?MatrizEspecies
    {
        $model = MatrizEspeciesEloquentModel::with('registros')
            ->where('solicitud_id', $solicitudId)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->reconstituir($model);
    }

    /**
     * Reconstituye la entidad de dominio a partir del modelo Eloquent.
     */
    private function reconstituir(MatrizEspeciesEloquentModel $model): MatrizEspecies
    {
        $registros = [];

        foreach ($model->registros as $regModel) {
            $registros[$regModel->id] = RegistroEspecimen::reconstituir(
                id: RegistroEspecimenId::from($regModel->id),
                nombreCientifico: $regModel->nombre_cientifico,
                nombreCorregido: $regModel->nombre_corregido,
                estado: EstadoRegistroEspecimen::from($regModel->estado),
                noCatalogado: (bool) $regModel->no_catalogado,
                motivoJustificacion: $regModel->motivo_justificacion,
                datosDwC: $regModel->datos_dwc ?? [],
                normalizaciones: $regModel->normalizaciones ?? [],
            );
        }

        return MatrizEspecies::reconstituir(
            id: MatrizEspeciesId::from($model->id),
            solicitudId: $model->solicitud_id,
            tipoTramite: TipoTramite::from($model->tipo_tramite),
            estado: EstadoMatrizEspecies::from($model->estado),
            camposDwCPresentes: $model->campos_dwc_presentes ?? [],
            registros: $registros,
            identificacionOriginalConservada: (bool) $model->identificacion_original_conservada,
        );
    }
}
