<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ItemPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

/**
 * Implementación Eloquent del repositorio de solicitudes de préstamo.
 *
 * Persiste y recupera el agregado {@see SolicitudPrestamo} y sus ítems relacionados.
 */
final class EloquentSolicitudPrestamoRepository implements SolicitudPrestamoRepositoryInterface
{
    /**
     * Persiste una solicitud de préstamo y sincroniza sus ítems.
     */
    public function guardar(SolicitudPrestamo $solicitud): void
    {
        $model = SolicitudPrestamoModel::updateOrCreate(
            ['id' => (string) $solicitud->id()],
            [
                'numero_solicitud' => (string) $solicitud->numeroSolicitud(),
                'investigador_id' => $solicitud->investigadorId(),
                'alcance_prestamo' => $solicitud->alcancePrestamo()->value,
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

    /**
     * Busca una solicitud por su identificador único.
     */
    public function buscarPorId(SolicitudPrestamoId $id): ?SolicitudPrestamo
    {
        $model = SolicitudPrestamoModel::with('items')->find((string) $id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    /**
     * Genera un nuevo identificador para una solicitud.
     */
    public function nextIdentity(): SolicitudPrestamoId
    {
        return SolicitudPrestamoId::generate();
    }

    /**
     * Resuelve el código externo legible de cada ítem de préstamo.
     *
     * @param  list<string>  $itemPrestamoIds
     * @return array<string, string>  Mapa [itemPrestamoId => códigoExterno].
     */
    public function mapaCodigosExternos(array $itemPrestamoIds): array
    {
        if ($itemPrestamoIds === []) {
            return [];
        }

        return ItemPrestamoModel::query()
            ->whereIn('id', $itemPrestamoIds)
            ->pluck('especimen_codigo_externo', 'id')
            ->all();
    }

    /**
     * Lista solicitudes para la bandeja del curador, aplicando filtros y orden.
     *
     * @param array<int, string>|null $investigadorIds
     * @return array<int, array{solicitudId: string, numeroSolicitud: string|null, tituloEstudio: string|null, investigadorId: string|null, estado: string, fecha: DateTimeImmutable}>
     */
    public function listarParaBandeja(
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array {
        $query = SolicitudPrestamoModel::query()
            ->whereNotIn('estado', ['borrador']);

        if ($busquedaTexto !== '') {
            $query->where(fn ($q) => $q
                ->where('titulo_estudio', 'ilike', "%{$busquedaTexto}%")
                ->orWhere('numero_solicitud', 'ilike', "%{$busquedaTexto}%")
            );
        }

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        if ($investigadorIds !== null) {
            $query->whereIn('investigador_id', $investigadorIds);
        }

        $campo = $ordenCampo === 'titulo' ? 'titulo_estudio' : 'created_at';
        $solicitudes = $query->orderBy($campo, $ordenDireccion)->get();

        return $solicitudes->map(fn (SolicitudPrestamoModel $solicitud): array => [
            'solicitudId' => (string) $solicitud->id,
            'numeroSolicitud' => $solicitud->numero_solicitud,
            'tituloEstudio' => $solicitud->titulo_estudio,
            'investigadorId' => $solicitud->investigador_id,
            'estado' => (string) $solicitud->estado,
            'fecha' => DateTimeImmutable::createFromInterface($solicitud->created_at),
        ])->values()->all();
    }

    /**
     * Lista las solicitudes de un investigador (incluye borradores) con el estado
     * del acta asociada si existe.
     *
     * @return array<int, array{solicitudId: string, numeroSolicitud: string|null, tituloEstudio: string|null, estado: string, fecha: DateTimeImmutable, actaId: string|null, actaEstado: string|null}>
     */
    public function listarPorInvestigador(
        string $investigadorId,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array {
        $query = SolicitudPrestamoModel::query()
            ->with('acta')
            ->where('investigador_id', $investigadorId);

        if ($busquedaTexto !== '') {
            $query->where(fn ($q) => $q
                ->where('titulo_estudio', 'ilike', "%{$busquedaTexto}%")
                ->orWhere('numero_solicitud', 'ilike', "%{$busquedaTexto}%")
            );
        }

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        $campo = $ordenCampo === 'titulo' ? 'titulo_estudio' : 'created_at';
        $solicitudes = $query->orderBy($campo, $ordenDireccion)->get();

        return $solicitudes->map(fn (SolicitudPrestamoModel $solicitud): array => [
            'solicitudId' => (string) $solicitud->id,
            'numeroSolicitud' => $solicitud->numero_solicitud,
            'tituloEstudio' => $solicitud->titulo_estudio,
            'estado' => (string) $solicitud->estado,
            'fecha' => DateTimeImmutable::createFromInterface($solicitud->created_at),
            'actaId' => $solicitud->acta?->id !== null ? (string) $solicitud->acta->id : null,
            'actaEstado' => $solicitud->acta?->estado,
        ])->values()->all();
    }

    /**
     * Sincroniza los ítems de la solicitud en la base de datos.
     *
     * @param SolicitudPrestamoModel $model
     * @param list<ItemPrestamo> $items
     */
    private function sincronizarItems(SolicitudPrestamoModel $model, array $items): void
    {
        $newIds = array_map(fn (ItemPrestamo $item) => (string) $item->id(), $items);

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

    /**
     * Mapea el modelo Eloquent y sus relaciones a la entidad de dominio.
     */
    private function toDomain(SolicitudPrestamoModel $model): SolicitudPrestamo
    {
        $items = $model->items->map(fn (ItemPrestamoModel $row) => ItemPrestamo::reconstituir(
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
            alcancePrestamo: AlcancePrestamo::from($model->alcance_prestamo),
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
