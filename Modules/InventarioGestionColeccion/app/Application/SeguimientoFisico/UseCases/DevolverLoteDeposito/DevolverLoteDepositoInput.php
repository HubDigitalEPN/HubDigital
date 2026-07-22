<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DevolverLoteDeposito;

/**
 * Entrada para marcar como devuelto un lote depositado.
 *
 * En primitivos: es el contrato que cruza desde GestionPrestamosRecepciones.
 *
 * @param  string[]  $codigosCatalogo
 */
final readonly class DevolverLoteDepositoInput
{
    public function __construct(
        public array $codigosCatalogo,
        public \DateTimeImmutable $devueltoEn,
    ) {}
}
