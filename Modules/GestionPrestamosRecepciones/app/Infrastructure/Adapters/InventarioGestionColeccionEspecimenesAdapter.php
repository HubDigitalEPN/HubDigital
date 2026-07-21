<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoEspecimenesPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EspecimenCatalogoDto;

/**
 * ACL — Anti-Corruption Layer entre GestionPrestamosRecepciones (Customer) e
 * InventarioGestionColeccion (Supplier).
 *
 * Es la única clase de este módulo que conoce el esquema `taxonomia`. Si el
 * inventario cambia sus tablas, solo este archivo se modifica. No importa ni una
 * clase del Domain o la Application del inventario: en un monolito, el único
 * punto de contacto legítimo entre bounded contexts es la base de datos.
 */
final class InventarioGestionColeccionEspecimenesAdapter implements CatalogoEspecimenesPort
{
    /**
     * Un espécimen solo es prestable si está disponible: uno ya prestado,
     * observado o extraviado no debe poder solicitarse.
     */
    private const ESTADO_PRESTABLE = 'disponible';

    public function buscarDisponibles(string $texto, int $limite = 15): array
    {
        $texto = trim($texto);

        // Sin término de búsqueda no se vuelca la colección entera.
        if ($texto === '') {
            return [];
        }

        $patron = '%'.$texto.'%';

        $filas = $this->consultaBase()
            ->where('e.estado', self::ESTADO_PRESTABLE)
            ->where(function (Builder $q) use ($patron): void {
                $q->where('e.codigo_catalogo', 'ilike', $patron)
                    ->orWhere('t.nombre_cientifico', 'ilike', $patron);
            })
            ->orderBy('e.codigo_catalogo')
            ->limit($limite)
            ->get();

        return $filas->map(fn ($fila) => $this->traducir($fila))->all();
    }

    public function obtenerPorId(string $especimenId): ?EspecimenCatalogoDto
    {
        $fila = $this->consultaBase()
            ->where('e.id', $especimenId)
            ->where('e.estado', self::ESTADO_PRESTABLE)
            ->first();

        return $fila === null ? null : $this->traducir($fila);
    }

    public function obtenerPorIds(array $especimenIds): array
    {
        if ($especimenIds === []) {
            return [];
        }

        $filas = $this->consultaBase()
            ->whereIn('e.id', $especimenIds)
            ->where('e.estado', self::ESTADO_PRESTABLE)
            ->get();

        $resultado = [];

        foreach ($filas as $fila) {
            $dto = $this->traducir($fila);
            $resultado[$dto->especimenId] = $dto;
        }

        return $resultado;
    }

    /**
     * LEFT JOIN y no INNER: `taxon_id` es nullable en el inventario, y un
     * espécimen sin determinación taxonómica sigue siendo prestable.
     */
    private function consultaBase(): Builder
    {
        return DB::table('taxonomia.especimenes as e')
            ->leftJoin('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->select([
                'e.id',
                'e.codigo_catalogo',
                'e.individual_count',
                'e.estado',
                't.nombre_cientifico',
            ]);
    }

    /** Único punto de traducción del esquema del inventario al lenguaje de préstamos. */
    private function traducir(mixed $fila): EspecimenCatalogoDto
    {
        return new EspecimenCatalogoDto(
            especimenId: (string) $fila->id,
            codigoCatalogo: (string) $fila->codigo_catalogo,
            nombreCientifico: $fila->nombre_cientifico,           // null si el espécimen no está determinado
            individualesDisponibles: (int) ($fila->individual_count ?? 0), // ACL: individual_count → individualesDisponibles; null se trata como 0
            estado: (string) $fila->estado,
        );
    }
}
