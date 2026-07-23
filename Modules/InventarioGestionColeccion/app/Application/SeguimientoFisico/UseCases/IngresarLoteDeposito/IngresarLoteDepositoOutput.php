<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito;

/**
 * Resultado de la ingesta: cuántos especímenes entraron, cuántos se omitieron por
 * haber sido ingresados antes y cuántos quedaron esperando revisión del curador.
 */
final readonly class IngresarLoteDepositoOutput
{
    /** @param string[] $codigosCreados */
    public function __construct(
        public int $especimenesCreados,
        public int $omitidosPorDuplicado,
        public int $marcadosParaRevision,
        public array $codigosCreados,
    ) {}
}
