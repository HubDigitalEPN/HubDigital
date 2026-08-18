<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoEspecimenesPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EspecimenCatalogoDto;
use Modules\GestionPrestamosRecepciones\Application\Ports\FichaEspecimenActaDto;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables\ConsultarEspecimenesPrestablesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables\ConsultarEspecimenesPrestablesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables\EspecimenPrestableDto;

/**
 * ACL — Anti-Corruption Layer entre GestionPrestamosRecepciones (Customer) e
 * InventarioGestionColeccion (Supplier).
 *
 * Reparte las lecturas según lo que codifiquen, siguiendo la regla del proyecto:
 *
 *  - **Con regla de negocio del inventario → su caso de uso.** Qué espécimen es
 *    prestable lo decide la colección, no este módulo. Antes se resolvía aquí con SQL
 *    propio y una copia de las reglas; cuando el inventario añadió el régimen de
 *    custodia, esta copia se quedó vieja y siguió ofreciendo material ya devuelto a su
 *    depositante.
 *  - **Proyección pura → SQL directo.** {@see obtenerFichasParaActa()} y la jerarquía de
 *    familias solo leen columnas para imprimirlas en un acta; no hay regla que se pueda
 *    quedar desactualizada, y a esa altura el espécimen ya está `en_prestamo`, así que
 *    los criterios de préstamo no le aplican.
 *
 * Sigue siendo la única clase de este módulo que conoce el esquema `taxonomia`.
 */
final class InventarioGestionColeccionEspecimenesAdapter implements CatalogoEspecimenesPort
{
    /** Rango del inventario que el acta imprime como familia. */
    private const RANGO_FAMILIA = 'familia';

    /** Rangos cuyo nombre científico es un género o una especie (o menor). */
    private const RANGOS_GENERO_ESPECIE = ['genero', 'especie', 'subespecie'];

    public function __construct(
        private readonly ConsultarEspecimenesPrestablesHandler $consultarPrestables,
    ) {}

    public function buscarDisponibles(string $texto, int $limite = 15): array
    {
        $texto = trim($texto);

        // Sin término de búsqueda no se vuelca la colección entera.
        if ($texto === '') {
            return [];
        }

        return array_map(
            fn (EspecimenPrestableDto $dto): EspecimenCatalogoDto => $this->traducirPrestable($dto),
            $this->consultarPrestables
                ->handle(ConsultarEspecimenesPrestablesInput::porTexto($texto, $limite))
                ->especimenes,
        );
    }

    public function obtenerPorId(string $especimenId): ?EspecimenCatalogoDto
    {
        $dto = $this->consultarPrestables
            ->handle(ConsultarEspecimenesPrestablesInput::porIds([$especimenId]))
            ->primero();

        return $dto === null ? null : $this->traducirPrestable($dto);
    }

    public function obtenerPorIds(array $especimenIds): array
    {
        if ($especimenIds === []) {
            return [];
        }

        $resultado = [];

        foreach ($this->consultarPrestables->handle(ConsultarEspecimenesPrestablesInput::porIds($especimenIds))->especimenes as $dto) {
            $resultado[$dto->especimenId] = $this->traducirPrestable($dto);
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

    /** Único punto de traducción del inventario al lenguaje de préstamos. */
    private function traducirPrestable(EspecimenPrestableDto $dto): EspecimenCatalogoDto
    {
        return new EspecimenCatalogoDto(
            especimenId: $dto->especimenId,
            codigoCatalogo: $dto->codigoCatalogo,
            nombreCientifico: $dto->nombreCientifico,   // null si el espécimen no está determinado
            // ACL: individualCount → individualesDisponibles. El null se preserva: el
            // inventario no registró el conteo, que no es lo mismo que no quedar ninguno.
            individualesDisponibles: $dto->individualCount,
            estado: $dto->estado,
        );
    }
}
