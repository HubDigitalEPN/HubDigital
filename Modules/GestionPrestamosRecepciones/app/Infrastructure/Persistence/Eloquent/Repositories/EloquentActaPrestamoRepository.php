<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

final class EloquentActaPrestamoRepository implements ActaPrestamoRepositoryInterface
{
    public function guardar(ActaPrestamo $acta): void
    {
        ActaPrestamoModel::updateOrCreate(
            ['id' => (string) $acta->id()],
            [
                'numero_prestamo' => (string) $acta->numeroPrestamo(),
                'solicitud_prestamo_id' => (string) $acta->solicitudPrestamoId(),
                'estado' => $acta->estado()->value,
                'tipo_prestamo' => $acta->tipoPrestamo()->value,
                'fecha_inicio' => $acta->fechaInicio()->format('Y-m-d'),
                'fecha_fin' => $acta->fechaFin()->format('Y-m-d'),
                'pdf_ruta' => $acta->pdfRuta(),
                'pdf_firmado_ruta' => $acta->pdfFirmadoRuta(),
                'firmada_subida_en' => $acta->firmadaSubidaEn()?->format('Y-m-d H:i:s'),
                'validada_en' => $acta->validadaEn()?->format('Y-m-d H:i:s'),
                'validada_por' => $acta->validadaPor(),
            ],
        );
    }

    public function buscarPorId(ActaPrestamoId $id): ?ActaPrestamo
    {
        $model = ActaPrestamoModel::find((string) $id);

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function buscarPorSolicitudId(SolicitudPrestamoId $solicitudId): ?ActaPrestamo
    {
        $model = ActaPrestamoModel::where('solicitud_prestamo_id', (string) $solicitudId)->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function nextIdentity(): ActaPrestamoId
    {
        return ActaPrestamoId::generate();
    }

    private function toDomain(ActaPrestamoModel $model): ActaPrestamo
    {
        return ActaPrestamo::reconstituir(
            id: ActaPrestamoId::fromString($model->id),
            numeroPrestamo: NumeroPrestamo::fromString($model->numero_prestamo),
            solicitudPrestamoId: SolicitudPrestamoId::fromString($model->solicitud_prestamo_id),
            estado: EstadoActa::from($model->estado),
            tipoPrestamo: TipoPrestamo::from($model->tipo_prestamo),
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: DateTimeImmutable::createFromInterface($model->fecha_fin),
            pdfRuta: $model->pdf_ruta,
            pdfFirmadoRuta: $model->pdf_firmado_ruta,
            firmadaSubidaEn: $model->firmada_subida_en !== null
                ? DateTimeImmutable::createFromInterface($model->firmada_subida_en)
                : null,
            validadaEn: $model->validada_en !== null
                ? DateTimeImmutable::createFromInterface($model->validada_en)
                : null,
            validadaPor: $model->validada_por,
        );
    }
}
