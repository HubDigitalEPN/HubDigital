<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito;

/**
 * Qué pasó con una fila concreta de la matriz al ingresar el lote.
 *
 * Los contadores agregados dicen cuántos entraron; esto dice cuál es cuál. Sirve para
 * dos cosas que antes no se podían hacer:
 *
 *  1. Que el módulo de recepciones anote en cada fila el espécimen que produjo, en el
 *     mismo momento del ingreso, sin depender de que alguien corra después un comando de
 *     reconstrucción.
 *  2. Que el curador vea por qué un registro concreto acabó en la cola de revisión, en
 *     vez de leer "26 marcados" y tener que ir a buscarlos uno a uno.
 */
final readonly class ResultadoFilaDeposito
{
    public const CREADO = 'creado';

    public const OMITIDO = 'omitido';

    public const CREADO_PARA_REVISION = 'creado_para_revision';

    public function __construct(
        public int $indiceMatriz,
        public ?string $registroId,
        public ?string $especimenId,
        public string $codigoCatalogo,
        public string $resultado,
        public ?string $motivoRevision = null,
    ) {}

    public function fueCreado(): bool
    {
        return $this->resultado !== self::OMITIDO;
    }

    public function requiereRevision(): bool
    {
        return $this->resultado === self::CREADO_PARA_REVISION;
    }
}
