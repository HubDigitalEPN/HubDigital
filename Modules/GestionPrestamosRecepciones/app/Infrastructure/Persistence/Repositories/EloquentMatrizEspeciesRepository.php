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

final class EloquentMatrizEspeciesRepository implements MatrizEspeciesRepositoryInterface
{
    public function nextIdentity(): MatrizEspeciesId
    {
        return MatrizEspeciesId::generate();
    }

    public function guardar(MatrizEspecies $matriz): void
    {
        MatrizEspeciesEloquentModel::updateOrCreate(
            ['id' => (string) $matriz->id()],
            [
                'solicitud_id' => $matriz->solicitudId(),
                'tipo_tramite' => $matriz->tipoTramite(),
                'estado' => $matriz->estado()->value,
                'campos_dwc_presentes' => $matriz->camposDwCPresentes(),
                'identificacion_original_conservada' => $matriz->identificacionOriginalConservada(),
            ]
        );

        $idsActuales = [];

        foreach ($matriz->registros() as $registroId => $registro) {
            RegistroEspecimenEloquentModel::updateOrCreate(
                ['id' => $registroId],
                [
                    'matriz_id' => (string) $matriz->id(),
                    'nombre_cientifico' => $registro->nombreCientifico(),
                    'nombre_corregido' => $registro->nombreCorregido(),
                    'estado' => $registro->estado()->value,
                    'no_catalogado' => $registro->esNoCatalogado(),
                    'motivo_justificacion' => $registro->motivoJustificacion(),
                ]
            );

            $idsActuales[] = $registroId;
        }

        RegistroEspecimenEloquentModel::where('matriz_id', (string) $matriz->id())
            ->whereNotIn('id', $idsActuales)
            ->delete();
    }

    public function buscarPorId(MatrizEspeciesId $matrizId): ?MatrizEspecies
    {
        $model = MatrizEspeciesEloquentModel::with('registros')->find((string) $matrizId);

        if ($model === null) {
            return null;
        }

        return $this->reconstituir($model);
    }

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
