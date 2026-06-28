<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

/**
 * Implementación Eloquent del repositorio de préstamos.
 *
 * Maneja la persistencia del agregado {@see Prestamo} en la base de datos.
 */
final class EloquentPrestamoRepository implements PrestamoRepositoryInterface
{
    /**
     * Persiste o actualiza un préstamo.
     */
    public function guardar(Prestamo $prestamo): void
    {
        PrestamoEloquentModel::updateOrCreate(
            ['id' => (string) $prestamo->id()],
            [
                'acta_prestamo_id' => (string) $prestamo->actaPrestamoId(),
                'codigo' => (string) $prestamo->codigoPrestamo(),
                'investigador_id' => $prestamo->investigadorId(),
                'estado' => $prestamo->estado()->value,
                'iniciado_en' => $prestamo->iniciadoEn()->format('Y-m-d H:i:s'),
                'fecha_fin' => $prestamo->fechaFin()->format('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * Busca un préstamo por su identificador.
     */
    public function buscarPorId(PrestamoId $id): ?Prestamo
    {
        $model = PrestamoEloquentModel::find((string) $id);

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Busca un préstamo por el identificador del acta asociada.
     */
    public function buscarPorActaId(ActaPrestamoId $actaId): ?Prestamo
    {
        $model = PrestamoEloquentModel::where('acta_prestamo_id', (string) $actaId)->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * Lista todos los préstamos que se encuentran en estado Activo.
     *
     * @return list<Prestamo>
     */
    public function listarActivos(): array
    {
        return PrestamoEloquentModel::where('estado', EstadoPrestamo::Activo->value)
            ->get()
            ->map(fn (PrestamoEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    /**
     * Genera un nuevo identificador único para un préstamo.
     */
    public function nextIdentity(): PrestamoId
    {
        return PrestamoId::generate();
    }

    /**
     * Lista préstamos para una bandeja, aplicando filtros y orden.
     *
     * @param array<int, string>|null $investigadorIds
     * @return array<int, array{prestamoId: string, numeroPrestamo: string|null, investigadorId: string|null, estado: string, iniciadoEn: DateTimeImmutable|null, fechaFin: DateTimeImmutable|null}>
     */
    public function listarParaBandeja(
        ?string $investigadorId,
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array {
        $query = PrestamoEloquentModel::query()->with('acta');

        if ($investigadorId !== null) {
            $query->where('investigador_id', $investigadorId);
        }

        if ($busquedaTexto !== '') {
            $query->whereHas('acta', fn ($q) => $q->where('codigo', 'ilike', "%{$busquedaTexto}%"));
        }

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        if ($investigadorIds !== null) {
            $query->whereIn('investigador_id', $investigadorIds);
        }

        $campo = $ordenCampo === 'fecha_vencimiento' ? 'fecha_fin' : 'iniciado_en';
        $prestamos = $query->orderBy($campo, $ordenDireccion)->get();

        return $prestamos->map(fn (PrestamoEloquentModel $prestamo): array => [
            'prestamoId' => (string) $prestamo->id,
            'numeroPrestamo' => $prestamo->codigo ?? $prestamo->acta?->codigo,
            'investigadorId' => $prestamo->investigador_id,
            'estado' => (string) $prestamo->estado,
            'iniciadoEn' => $prestamo->iniciado_en !== null
                ? DateTimeImmutable::createFromInterface($prestamo->iniciado_en)
                : null,
            'fechaFin' => $prestamo->fecha_fin !== null
                ? DateTimeImmutable::createFromInterface($prestamo->fecha_fin)
                : null,
        ])->values()->all();
    }

    /**
     * Convierte el modelo Eloquent a la entidad de dominio Prestamo.
     */
    private function toDomain(PrestamoEloquentModel $model): Prestamo
    {
        return Prestamo::reconstituir(
            id: PrestamoId::fromString($model->id),
            actaPrestamoId: ActaPrestamoId::fromString($model->acta_prestamo_id),
            codigoPrestamo: CodigoPrestamo::fromString($model->codigo),
            investigadorId: $model->investigador_id,
            estado: EstadoPrestamo::from($model->estado),
            iniciadoEn: DateTimeImmutable::createFromInterface($model->iniciado_en),
            fechaFin: DateTimeImmutable::createFromInterface($model->fecha_fin),
        );
    }
}
