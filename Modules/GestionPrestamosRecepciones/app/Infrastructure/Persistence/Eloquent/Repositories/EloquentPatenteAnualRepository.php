<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\PatenteAnualRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\Patente;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PatenteAnualEloquentModel;

/**
 * Implementación Eloquent del repositorio de patentes anuales (una por año).
 */
final class EloquentPatenteAnualRepository implements PatenteAnualRepositoryInterface
{
    public function guardarParaAnio(int $anio, Patente $codigo, string $curadorId): void
    {
        PatenteAnualEloquentModel::updateOrCreate(
            ['anio' => $anio],
            [
                'id' => (string) Str::uuid(),
                'codigo' => $codigo->valor(),
                'curador_id' => $curadorId,
            ],
        );
    }

    public function buscarCodigoPorAnio(int $anio): ?string
    {
        return PatenteAnualEloquentModel::query()
            ->where('anio', $anio)
            ->value('codigo');
    }
}
