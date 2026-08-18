<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito;

/**
 * Resultado de la ingesta: cuántos especímenes entraron, cuántos se omitieron por
 * haber sido ingresados antes y cuántos quedaron esperando revisión del curador.
 *
 * `resultados` añade el detalle fila a fila. Los contadores dicen cuántos; esto dice
 * cuáles, que es lo que necesita el módulo de recepciones para cerrar el vínculo con
 * cada registro de su matriz sin volver a calcularlo por su cuenta.
 */
final readonly class IngresarLoteDepositoOutput
{
    /**
     * @param  string[]  $codigosCreados
     * @param  ResultadoFilaDeposito[]  $resultados
     */
    public function __construct(
        public int $especimenesCreados,
        public int $omitidosPorDuplicado,
        public int $marcadosParaRevision,
        public array $codigosCreados,
        public array $resultados = [],
    ) {}

    /**
     * Mapa de la fila de matriz al espécimen que produjo.
     *
     * Solo incluye las filas que traían identidad de registro y acabaron en un espécimen:
     * es exactamente lo que se puede anotar del otro lado.
     *
     * @return array<string, string> registroId => especimenId
     */
    public function especimenPorRegistro(): array
    {
        $mapa = [];

        foreach ($this->resultados as $resultado) {
            if ($resultado->registroId !== null && $resultado->especimenId !== null) {
                $mapa[$resultado->registroId] = $resultado->especimenId;
            }
        }

        return $mapa;
    }
}
