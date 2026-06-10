<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEntregaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEntregaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoEnvio;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEntregaId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\VerificacionEntregaPrestamoEloquentModel;

/**
 * Implementación Eloquent del repositorio de verificaciones de entrega.
 *
 * Persiste y recupera el agregado {@see VerificacionEntregaPrestamo} que detalla
 * el estado de los especímenes recibidos por el investigador.
 */
final class EloquentVerificacionEntregaPrestamoRepository implements VerificacionEntregaPrestamoRepositoryInterface
{
    /**
     * Persiste una verificación de entrega.
     */
    public function guardar(VerificacionEntregaPrestamo $verificacion): void
    {
        $observaciones = array_map(
            fn (ObservacionEspecimen $obs): array => [
                'item_prestamo_id' => $obs->itemPrestamoId,
                'descripcion' => $obs->descripcion,
            ],
            $verificacion->observaciones(),
        );

        VerificacionEntregaPrestamoEloquentModel::updateOrCreate(
            ['id' => (string) $verificacion->id()],
            [
                'prestamo_id' => (string) $verificacion->prestamoId(),
                'estado_envio' => $verificacion->estadoEnvio()->value,
                'observaciones' => $observaciones,
            ],
        );
    }

    /**
     * Busca la verificación de entrega asociada a un préstamo.
     */
    public function buscarPorPrestamoId(PrestamoId $prestamoId): ?VerificacionEntregaPrestamo
    {
        $model = VerificacionEntregaPrestamoEloquentModel::where('prestamo_id', (string) $prestamoId)->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Genera un nuevo identificador para una verificación.
     */
    public function nextIdentity(): VerificacionEntregaId
    {
        return VerificacionEntregaId::generate();
    }

    /**
     * Mapea el modelo Eloquent a la entidad de dominio.
     */
    private function toDomain(VerificacionEntregaPrestamoEloquentModel $model): VerificacionEntregaPrestamo
    {
        $observaciones = array_map(
            fn (array $obs): ObservacionEspecimen => new ObservacionEspecimen(
                itemPrestamoId: $obs['item_prestamo_id'],
                descripcion: $obs['descripcion'],
            ),
            $model->observaciones ?? [],
        );

        return VerificacionEntregaPrestamo::reconstituir(
            id: VerificacionEntregaId::fromString($model->id),
            prestamoId: PrestamoId::fromString($model->prestamo_id),
            estadoEnvio: EstadoEnvio::from($model->estado_envio),
            observaciones: $observaciones,
        );
    }
}
