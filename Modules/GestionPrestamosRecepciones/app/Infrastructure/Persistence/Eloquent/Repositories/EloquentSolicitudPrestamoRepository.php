<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ItemPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

final class EloquentSolicitudPrestamoRepository implements SolicitudPrestamoRepositoryInterface
{
    public function guardar(SolicitudPrestamo $solicitud): void
    {
        $model = SolicitudPrestamoModel::updateOrCreate(
            ['id' => (string) $solicitud->id()],
            [
                'numero_solicitud' => (string) $solicitud->numeroSolicitud(),
                'investigador_id' => $solicitud->investigadorId(),
                'estado' => $solicitud->estado()->value,
                'titulo_estudio' => $solicitud->tituloEstudio(),
                'institucion_adscripcion' => $solicitud->institucionAdscripcion(),
                'linea_investigacion' => $solicitud->lineaInvestigacion(),
                'proposito_prestamo' => $solicitud->propositoPrestamo(),
                'duracion_propuesta_meses' => $solicitud->duracionPropuestaMeses(),
                'justificacion_extendida' => $solicitud->justificacionExtendida(),
                'comentario_curador' => $solicitud->comentarioCurador(),
                'enviada_en' => $solicitud->enviadaEn()?->format('Y-m-d H:i:s'),
                'resuelta_en' => $solicitud->resueltaEn()?->format('Y-m-d H:i:s'),
                'resuelta_por' => $solicitud->resueltaPor(),
            ],
        );

        $this->sincronizarItems($model, $solicitud->items());
    }

    public function buscarPorId(SolicitudPrestamoId $id): ?SolicitudPrestamo
    {
        $model = SolicitudPrestamoModel::with('items')->find((string) $id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function nextIdentity(): SolicitudPrestamoId
    {
        return SolicitudPrestamoId::generate();
    }

    /** @param list<ItemPrestamo> $items */
    private function sincronizarItems(SolicitudPrestamoModel $model, array $items): void
    {
        $newIds = array_map(fn(ItemPrestamo $item) => (string) $item->id(), $items);

        foreach ($items as $item) {
            ItemPrestamoModel::updateOrCreate(
                ['id' => (string) $item->id()],
                [
                    'solicitud_prestamo_id' => $model->id,
                    'especimen_codigo_externo' => $item->especimenCodigoExterno(),
                    'cantidad_solicitada' => $item->cantidadSolicitada(),
                    'especimen_snapshot' => $item->especimenSnapshot(),
                    'condiciones_especificas' => $item->condicionesEspecificas(),
                ],
            );
        }

        ItemPrestamoModel::where('solicitud_prestamo_id', $model->id)
            ->whereNotIn('id', $newIds)
            ->delete();
    }

    private function toDomain(SolicitudPrestamoModel $model): SolicitudPrestamo
    {
        $items = $model->items->map(fn(ItemPrestamoModel $row) => ItemPrestamo::reconstituir(
            id: ItemPrestamoId::fromString($row->id),
            especimenCodigoExterno: $row->especimen_codigo_externo,
            cantidadSolicitada: $row->cantidad_solicitada,
            especimenSnapshot: $row->especimen_snapshot,
            condicionesEspecificas: $row->condiciones_especificas,
        ))->all();

        return SolicitudPrestamo::reconstituir(
            id: SolicitudPrestamoId::fromString($model->id),
            numeroSolicitud: NumeroSolicitud::fromString($model->numero_solicitud),
            investigadorId: $model->investigador_id,
            estado: EstadoSolicitud::from($model->estado),
            tituloEstudio: $model->titulo_estudio,
            institucionAdscripcion: $model->institucion_adscripcion,
            lineaInvestigacion: $model->linea_investigacion,
            propositoPrestamo: $model->proposito_prestamo,
            duracionPropuestaMeses: $model->duracion_propuesta_meses,
            justificacionExtendida: $model->justificacion_extendida,
            comentarioCurador: $model->comentario_curador,
            items: $items,
            enviadaEn: $model->enviada_en !== null
                ? DateTimeImmutable::createFromInterface($model->enviada_en)
                : null,
            resueltaEn: $model->resuelta_en !== null
                ? DateTimeImmutable::createFromInterface($model->resuelta_en)
                : null,
            resueltaPor: $model->resuelta_por,
        );
    }
}
