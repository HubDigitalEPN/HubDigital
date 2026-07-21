<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Resultado del ingreso de un lote a la colección, tal como lo devuelve el módulo
 * de inventario. En primitivos: este módulo no conoce los tipos de aquel.
 */
final readonly class ResultadoIngresoColeccion
{
    public function __construct(
        public int $especimenesCreados,
        public int $omitidosPorDuplicado,
        public int $marcadosParaRevision,
    ) {}
}
