<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AccionCorrectiva;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DocumentoAdjunto;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoColeccion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemVerificacionRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MotivoFalloRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecepcionLoteId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;

final class EloquentRecepcionLoteRepository implements RecepcionLoteRepositoryInterface
{
    public function nextIdentity(): RecepcionLoteId
    {
        return RecepcionLoteId::generate();
    }

    public function guardar(RecepcionLote $lote): void
    {
        $acta = $lote->actaRecepcion();

        RecepcionLoteEloquentModel::updateOrCreate(
            ['id' => (string) $lote->id()],
            [
                'solicitud_deposito_id' => (string) $lote->solicitudId(),
                'codigo_qr' => (string) $lote->codigoQR(),
                'tipo_tramite' => $lote->tipoTramite()->value,
                'estado' => $lote->estado()->value,
                'estado_coleccion' => $lote->estadoColeccion()?->value,
                'motivo_fallo' => $lote->motivoFallo()?->value,
                'accion_correctiva' => $lote->accionCorrectiva()?->value,
                'items_verificacion' => array_values(array_map(
                    fn (ItemVerificacionRecepcion $item): array => [
                        'item' => $item->item,
                        'resultado' => $item->resultado,
                    ],
                    $lote->itemsVerificacion(),
                )),
                'observaciones' => array_values($lote->observaciones()),
                'acta_recepcion' => $acta !== null
                    ? ['nombre' => $acta->nombre(), 'ruta' => $acta->ruta()]
                    : null,
            ],
        );
    }

    public function buscarPorId(RecepcionLoteId $id): ?RecepcionLote
    {
        $model = RecepcionLoteEloquentModel::find((string) $id);

        return $model !== null ? $this->reconstituir($model) : null;
    }

    public function buscarPorSolicitudId(SolicitudDepositoId $solicitudId): ?RecepcionLote
    {
        $model = RecepcionLoteEloquentModel::where('solicitud_deposito_id', (string) $solicitudId)->first();

        return $model !== null ? $this->reconstituir($model) : null;
    }

    public function buscarPorCodigoQR(CodigoQRLote $codigoQR): ?RecepcionLote
    {
        $model = RecepcionLoteEloquentModel::where('codigo_qr', (string) $codigoQR)->first();

        return $model !== null ? $this->reconstituir($model) : null;
    }

    private function reconstituir(RecepcionLoteEloquentModel $model): RecepcionLote
    {
        $items = array_map(
            fn (array $item): ItemVerificacionRecepcion => new ItemVerificacionRecepcion(
                item: $item['item'],
                resultado: $item['resultado'],
            ),
            $model->items_verificacion ?? [],
        );

        $acta = $model->acta_recepcion;

        return RecepcionLote::reconstituir(
            id: RecepcionLoteId::fromString($model->id),
            solicitudId: SolicitudDepositoId::from($model->solicitud_deposito_id),
            codigoQR: CodigoQRLote::from($model->codigo_qr),
            tipoTramite: TipoTramite::from($model->tipo_tramite),
            estado: EstadoRecepcionLote::from($model->estado),
            itemsVerificacion: $items,
            observaciones: array_values($model->observaciones ?? []),
            estadoColeccion: $model->estado_coleccion !== null ? EstadoColeccion::from($model->estado_coleccion) : null,
            motivoFallo: $model->motivo_fallo !== null ? MotivoFalloRecepcion::from($model->motivo_fallo) : null,
            accionCorrectiva: $model->accion_correctiva !== null ? AccionCorrectiva::from($model->accion_correctiva) : null,
            actaRecepcion: $acta !== null ? DocumentoAdjunto::of($acta['nombre'], $acta['ruta']) : null,
        );
    }
}
