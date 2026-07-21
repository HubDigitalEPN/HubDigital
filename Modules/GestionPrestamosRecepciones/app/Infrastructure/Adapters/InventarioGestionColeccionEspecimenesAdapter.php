<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoEspecimenesPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EspecimenCatalogoDto;
use Modules\GestionPrestamosRecepciones\Application\Ports\FichaEspecimenActaDto;

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

    /**
     * Situaciones Darwin Core que sacan al espécimen de la colección prestable, aunque
     * su `estado` siga diciendo 'disponible': la importación del CSV dejó ese estado por
     * defecto sin mirar `occurrence_status`.
     */
    private const OCCURRENCE_STATUS_NO_PRESTABLE = ['loaned', 'destroyed'];

    /** Única constancia Darwin Core de que el espécimen sigue en la colección. */
    private const OCCURRENCE_STATUS_PRESENTE = 'present';

    /** Rango del inventario que el acta imprime como familia. */
    private const RANGO_FAMILIA = 'familia';

    /** Rangos cuyo nombre científico es un género o una especie (o menor). */
    private const RANGOS_GENERO_ESPECIE = ['genero', 'especie', 'subespecie'];

    public function buscarDisponibles(string $texto, int $limite = 15): array
    {
        $texto = trim($texto);

        // Sin término de búsqueda no se vuelca la colección entera.
        if ($texto === '') {
            return [];
        }

        $patron = '%'.$texto.'%';

        $filas = $this->consultaBase()
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
            ->get();

        $resultado = [];

        foreach ($filas as $fila) {
            $dto = $this->traducir($fila);
            $resultado[$dto->especimenId] = $dto;
        }

        return $resultado;
    }

    public function obtenerFichasParaActa(array $especimenIds): array
    {
        if ($especimenIds === []) {
            return [];
        }

        // Sin los filtros de consultaBase(): a la hora del acta el espécimen ya está
        // 'en_prestamo' y el documento igual tiene que describirlo.
        $filas = DB::table('taxonomia.especimenes as e')
            ->leftJoin('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->whereIn('e.id', $especimenIds)
            ->select([
                'e.id',
                'e.codigo_catalogo',
                'e.sex',
                'e.state_province',
                'e.locality_name',
                'e.taxon_id',
                't.nombre_cientifico',
                't.rango',
            ])
            ->get();

        $familias = $this->familiasPorTaxon(
            $filas->pluck('taxon_id')->filter()->unique()->values()->all()
        );

        $resultado = [];

        foreach ($filas as $fila) {
            $resultado[(string) $fila->id] = new FichaEspecimenActaDto(
                especimenId: (string) $fila->id,
                codigoCatalogo: (string) $fila->codigo_catalogo,
                familia: $familias[(string) $fila->taxon_id] ?? null,
                sexo: $this->traducirSexo($fila->sex),
                // Solo se imprime como especie lo que de verdad lo es: un espécimen
                // determinado únicamente hasta 'reino' no debe mostrar "Animalia" ahí.
                especie: in_array($fila->rango, self::RANGOS_GENERO_ESPECIE, true)
                    ? $fila->nombre_cientifico
                    : null,
                provincia: $fila->state_province,
                localidad: $fila->locality_name,
            );
        }

        return $resultado;
    }

    /**
     * Traduce el `sex` Darwin Core al español para imprimirlo en el acta.
     *
     * El dato del inventario no está normalizado del todo (conviven 'female', 'F',
     * 'H', 'Male', 'nd', '?'), así que se compara en minúsculas y lo desconocido se
     * imprime tal cual en lugar de perderse.
     */
    private function traducirSexo(?string $sex): ?string
    {
        $sex = trim((string) $sex);

        if ($sex === '') {
            return null;
        }

        return match (mb_strtolower($sex)) {
            'male', 'm', 'macho' => 'Macho',
            'female', 'f', 'h', 'hembra' => 'Hembra',
            'male/female', 'm/f' => 'Macho/Hembra',
            'unknown', 'nd', '?' => 'Indeterminado',
            default => $sex,
        };
    }

    /**
     * Resuelve la familia de varios taxones en una sola consulta, subiendo por
     * `padre_id` hasta encontrar el ancestro de rango 'familia'.
     *
     * @param  list<string>  $taxonIds
     * @return array<string, string> taxonId => nombre de la familia. Los taxones cuya
     *                               rama no llega a familia no aparecen.
     */
    private function familiasPorTaxon(array $taxonIds): array
    {
        if ($taxonIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taxonIds), '?'));

        // El corte por profundidad es el anticiclo (la jerarquía se autorreferencia y un
        // padre_id mal cargado colgaría la consulta); el corte por rango detiene el
        // ascenso en cuanto aparece la familia.
        $filas = DB::select(<<<SQL
            WITH RECURSIVE cadena AS (
                SELECT t.id AS raiz, t.id, t.rango, t.nombre_cientifico, t.padre_id, 0 AS profundidad
                FROM taxonomia.taxones t
                WHERE t.id IN ($placeholders)
                UNION ALL
                SELECT c.raiz, p.id, p.rango, p.nombre_cientifico, p.padre_id, c.profundidad + 1
                FROM cadena c
                JOIN taxonomia.taxones p ON p.id = c.padre_id
                WHERE c.profundidad < 20 AND c.rango <> ?
            )
            SELECT raiz, nombre_cientifico FROM cadena WHERE rango = ?
            SQL, [...$taxonIds, self::RANGO_FAMILIA, self::RANGO_FAMILIA]);

        $familias = [];

        foreach ($filas as $fila) {
            $familias[(string) $fila->raiz] = (string) $fila->nombre_cientifico;
        }

        return $familias;
    }

    /**
     * Define qué es un espécimen prestable, para los tres métodos del puerto: la
     * búsqueda del investigador y las dos revalidaciones del servidor comparten
     * criterio, así que ninguno puede quedarse atrás.
     *
     * LEFT JOIN y no INNER: `taxon_id` es nullable en el inventario, y un
     * espécimen sin determinación taxonómica sigue siendo prestable.
     */
    private function consultaBase(): Builder
    {
        return DB::table('taxonomia.especimenes as e')
            ->leftJoin('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->where('e.estado', self::ESTADO_PRESTABLE)
            // Segundo filtro sobre el mismo hecho: un espécimen prestado o destruido no
            // vuelve a prestarse aunque su `estado` no se haya actualizado nunca.
            // El OR sobre null es obligatorio: `NOT IN` deja fuera las filas nulas.
            ->where(function (Builder $q): void {
                $q->whereNull('e.occurrence_status')
                    ->orWhereNotIn('e.occurrence_status', self::OCCURRENCE_STATUS_NO_PRESTABLE);
            })
            // Solo se ofrece lo que consta en la colección. Basta una de las dos
            // evidencias: quedan individuos contados, o el registro Darwin Core declara
            // el espécimen presente aunque nadie haya anotado cuántos. Sin ninguna de
            // las dos no se muestra, y un conteo en cero nunca alcanza.
            ->where(function (Builder $q): void {
                $q->where('e.individual_count', '>', 0)
                    ->orWhere(function (Builder $sinConteo): void {
                        $sinConteo->whereNull('e.individual_count')
                            ->where('e.occurrence_status', self::OCCURRENCE_STATUS_PRESENTE);
                    });
            })
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
            // ACL: individual_count → individualesDisponibles. El null se preserva: el
            // inventario no registró el conteo, que no es lo mismo que no quedar ninguno.
            individualesDisponibles: $fila->individual_count === null ? null : (int) $fila->individual_count,
            estado: (string) $fila->estado,
        );
    }
}
