<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

/**
 * Implementación Eloquent del repositorio de actas de préstamo.
 *
 * Persiste y recupera agregados {@see ActaPrestamo} utilizando el modelo
 * Eloquent {@see ActaPrestamoModel}.
 */
final class EloquentActaPrestamoRepository implements ActaPrestamoRepositoryInterface
{
    /**
     * Persiste un acta de préstamo.
     */
    public function guardar(ActaPrestamo $acta): void
    {
        ActaPrestamoModel::updateOrCreate(
            ['id' => (string) $acta->id()],
            [
                'codigo' => (string) $acta->codigoPrestamo(),
                'solicitud_prestamo_id' => (string) $acta->solicitudPrestamoId(),
                'estado' => $acta->estado()->value,
                'tipo_prestamo' => $acta->tipoPrestamo()->value,
                'alcance_prestamo' => $acta->alcancePrestamo()->value,
                'fecha_inicio' => $acta->fechaInicio()->format('Y-m-d'),
                'fecha_fin' => $acta->fechaFin()->format('Y-m-d'),
                'pdf_ruta' => $acta->pdfRuta(),
                'condiciones_generales' => $acta->condicionesGenerales(),
                'pdf_firmado_ruta' => $acta->pdfFirmadoRuta(),
                'documento_identidad_ruta' => $acta->documentoIdentidadRuta(),
                'documento_exportacion_ruta' => $acta->documentoExportacionRuta(),
                'motivo_devolucion' => $acta->motivoDevolucion(),
                'firmada_subida_en' => $acta->firmadaSubidaEn()?->format('Y-m-d H:i:s'),
                'validada_en' => $acta->validadaEn()?->format('Y-m-d H:i:s'),
                'validada_por' => $acta->validadaPor(),
                'pdf_firmado_curador_ruta' => $acta->pdfFirmadoCuradorRuta(),
            ],
        );
    }

    /**
     * Busca un acta por su identificador único.
     */
    public function buscarPorId(ActaPrestamoId $id): ?ActaPrestamo
    {
        $model = ActaPrestamoModel::find((string) $id);

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Busca el acta asociada a una solicitud de préstamo específica.
     */
    public function buscarPorSolicitudId(SolicitudPrestamoId $solicitudId): ?ActaPrestamo
    {
        $model = ActaPrestamoModel::where('solicitud_prestamo_id', (string) $solicitudId)->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Genera un nuevo identificador para un acta.
     */
    public function nextIdentity(): ActaPrestamoId
    {
        return ActaPrestamoId::generate();
    }

    /**
     * Lista actas para la bandeja del curador, aplicando filtros y orden.
     *
     * @param  array<int, string>|null  $investigadorIds
     * @return array<int, array{actaId: string, numeroPrestamo: string, numeroSolicitud: string|null, investigadorId: string|null, estado: string, fecha: DateTimeImmutable}>
     */
    public function listarParaBandeja(
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array {
        $query = ActaPrestamoModel::query()->with('solicitud');

        if ($busquedaTexto !== '') {
            $query->where(fn ($q) => $q
                ->where('codigo', 'ilike', "%{$busquedaTexto}%")
                ->orWhereHas('solicitud', fn ($sq) => $sq
                    ->where('codigo', 'ilike', "%{$busquedaTexto}%")
                    ->orWhere('titulo_estudio', 'ilike', "%{$busquedaTexto}%")
                )
            );
        }

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        if ($investigadorIds !== null) {
            $query->whereHas('solicitud', fn ($q) => $q->whereIn('investigador_id', $investigadorIds));
        }

        if ($ordenCampo === 'codigo') {
            $actas = $query->get();
            $actas = $ordenDireccion === 'asc'
                ? $actas->sortBy('codigo')
                : $actas->sortByDesc('codigo');
        } else {
            $actas = $query->orderBy('created_at', $ordenDireccion)->get();
        }

        return $actas->map(fn (ActaPrestamoModel $acta): array => [
            'actaId' => (string) $acta->id,
            'numeroPrestamo' => (string) $acta->codigo,
            'numeroSolicitud' => $acta->solicitud?->codigo,
            'investigadorId' => $acta->solicitud?->investigador_id,
            'estado' => (string) $acta->estado,
            'fecha' => DateTimeImmutable::createFromInterface($acta->created_at),
        ])->values()->all();
    }

    /**
     * Mapea el modelo Eloquent a la entidad de dominio.
     */
    private function toDomain(ActaPrestamoModel $model): ActaPrestamo
    {
        return ActaPrestamo::reconstituir(
            id: ActaPrestamoId::fromString($model->id),
            codigoPrestamo: CodigoPrestamo::fromString($model->codigo),
            solicitudPrestamoId: SolicitudPrestamoId::fromString($model->solicitud_prestamo_id),
            estado: EstadoActa::from($model->estado),
            tipoPrestamo: TipoPrestamo::from($model->tipo_prestamo),
            alcancePrestamo: AlcancePrestamo::from($model->alcance_prestamo),
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: DateTimeImmutable::createFromInterface($model->fecha_fin),
            pdfRuta: $model->pdf_ruta,
            condicionesGenerales: $model->condiciones_generales,
            pdfFirmadoRuta: $model->pdf_firmado_ruta,
            documentoIdentidadRuta: $model->documento_identidad_ruta,
            documentoExportacionRuta: $model->documento_exportacion_ruta,
            motivoDevolucion: $model->motivo_devolucion,
            firmadaSubidaEn: $model->firmada_subida_en !== null
                ? DateTimeImmutable::createFromInterface($model->firmada_subida_en)
                : null,
            validadaEn: $model->validada_en !== null
                ? DateTimeImmutable::createFromInterface($model->validada_en)
                : null,
            validadaPor: $model->validada_por,
            pdfFirmadoCuradorRuta: $model->pdf_firmado_curador_ruta,
        );
    }
}
