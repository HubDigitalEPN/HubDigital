<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de escritura sobre el estado de los especímenes del catálogo.
 *
 * Complementa a {@see CatalogoEspecimenesPort}, que es de solo lectura. Este módulo
 * no decide *cómo* cambia el estado de un espécimen —esa regla pertenece al
 * inventario, que es su dueño—; solo declara los dos hechos del ciclo de préstamo
 * que obligan a que cambie.
 */
interface EstadoEspecimenCatalogoPort
{
    /**
     * Los especímenes salen de circulación porque el préstamo pasó a activo.
     *
     * @param  string[]  $especimenIds
     */
    public function marcarEnPrestamo(array $especimenIds): void;

    /**
     * Los especímenes vuelven al catálogo porque el préstamo se cerró.
     *
     * @param  string[]  $especimenIds  Todos los especímenes devueltos.
     * @param  string[]  $conNovedad  Subconjunto que el curador verificó con observación.
     */
    public function restaurar(array $especimenIds, array $conNovedad): void;
}
