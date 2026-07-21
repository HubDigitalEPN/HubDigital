<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito;

/**
 * Entrada de la ingesta de un lote depositado.
 *
 * Deliberadamente en primitivos: es el contrato que cruza desde
 * GestionPrestamosRecepciones, y ninguno de los dos módulos debe conocer los tipos
 * de dominio del otro.
 *
 * @param  array<int, array{indice: int, datosDwC: array<string, mixed>, estadoRegistro: string, motivoJustificacion?: string|null}>  $filas
 */
final readonly class IngresarLoteDepositoInput
{
    public function __construct(
        public string $numeroSolicitud,
        public string $actaRecepcion,
        public string $estadoCustodia,
        public array $filas,
        public ?string $entidadDepositanteId = null,
    ) {}
}
