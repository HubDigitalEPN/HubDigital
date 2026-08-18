<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Resultado del ingreso de un lote a la colección, tal como lo devuelve el módulo
 * de inventario. En primitivos: este módulo no conoce los tipos de aquel.
 *
 * `filasAnotadas` cuenta los registros de la matriz a los que se les pudo apuntar el
 * espécimen que produjeron. Antes ese vínculo solo existía si alguien corría después el
 * comando de reconstrucción; ahora se cierra en el mismo momento del ingreso.
 *
 * `motivosRevision` lleva, por registro, por qué acabó en la cola del curador: los
 * contadores decían cuántos, no cuáles.
 */
final readonly class ResultadoIngresoColeccion
{
    /** @param array<string, string> $motivosRevision registroId => motivo */
    public function __construct(
        public int $especimenesCreados,
        public int $omitidosPorDuplicado,
        public int $marcadosParaRevision,
        public int $filasAnotadas = 0,
        public array $motivosRevision = [],
    ) {}
}
