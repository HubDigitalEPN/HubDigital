<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DevolverLoteDeposito;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Registra que un lote depositado volvió a manos de su depositante.
 *
 * No borra nada: los especímenes quedan con `estado_custodia = 'Devuelto'` y su fecha
 * de salida. En una colección científica el rastro de qué material estuvo bajo custodia
 * y cuándo salió es parte de la documentación, y el Acta de Recepción sigue apuntando
 * a esos registros.
 *
 * Solo alcanza al material en custodia temporal, así que es inofensivo repetirlo y no
 * puede tocar donaciones, que son cesiones definitivas al patrimonio.
 */
final class DevolverLoteDepositoHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(DevolverLoteDepositoInput $input): DevolverLoteDepositoOutput
    {
        return new DevolverLoteDepositoOutput(
            especimenesDevueltos: $this->especimenRepo->marcarDevueltosPorCodigosCatalogo(
                $input->codigosCatalogo,
                $input->devueltoEn,
            ),
        );
    }
}
