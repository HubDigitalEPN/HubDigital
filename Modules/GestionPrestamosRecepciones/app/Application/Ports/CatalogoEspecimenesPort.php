<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de consulta al catálogo de especímenes del módulo de inventario.
 *
 * Lo implementa un adaptador ACL en Infrastructure que traduce el esquema del
 * inventario al vocabulario de este módulo. Este contrato es el único punto por
 * el que el proceso de préstamos conoce los especímenes: ninguna clase de este
 * módulo importa código del inventario.
 */
interface CatalogoEspecimenesPort
{
    /**
     * Busca especímenes prestables por código de catálogo o nombre científico.
     *
     * Devuelve únicamente los que están disponibles: un espécimen ya prestado,
     * observado o extraviado no debe poder solicitarse.
     *
     * @return list<EspecimenCatalogoDto> Vacío si el texto de búsqueda está vacío.
     */
    public function buscarDisponibles(string $texto, int $limite = 15): array;

    public function obtenerPorId(string $especimenId): ?EspecimenCatalogoDto;

    /**
     * Resuelve varios especímenes en una sola consulta.
     *
     * Existe para revalidar todos los ítems de una solicitud sin incurrir en N+1.
     * Los ids inexistentes simplemente no aparecen en el resultado.
     *
     * @param  list<string>  $especimenIds
     * @return array<string, EspecimenCatalogoDto> Indexado por especimenId.
     */
    public function obtenerPorIds(array $especimenIds): array;

    /**
     * Ficha descriptiva de cada espécimen para la hoja de especímenes del acta.
     *
     * A diferencia de {@see obtenerPorIds()}, NO filtra por disponibilidad: cuando el
     * acta se genera o se descarga el espécimen ya pasó a 'en_prestamo', y aun así el
     * documento debe describirlo.
     *
     * @param  list<string>  $especimenIds
     * @return array<string, FichaEspecimenActaDto> Indexado por especimenId.
     */
    public function obtenerFichasParaActa(array $especimenIds): array;
}
