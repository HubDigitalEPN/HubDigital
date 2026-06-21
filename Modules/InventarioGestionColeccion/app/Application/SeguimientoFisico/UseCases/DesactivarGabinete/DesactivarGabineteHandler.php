<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

/**
 * Caso de uso: marcar un gabinete como inactivo, retirándolo del uso operativo sin eliminarlo.
 *
 * @see DesactivarGabineteInput
 * @see DesactivarGabineteOutput
 */
final class DesactivarGabineteHandler
{
    /**
     * @param  GabineteRepository  $gabineteRepo  Recupera y persiste el gabinete.
     */
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    /**
     * Recupera el gabinete, lo desactiva y persiste el cambio.
     *
     * @throws \DomainException si el gabinete no existe.
     */
    public function handle(DesactivarGabineteInput $input): DesactivarGabineteOutput
    {
        $gabinete = $this->gabineteRepo->buscarPorId(GabineteId::desde($input->gabineteId));

        if ($gabinete === null) {
            throw new \DomainException('Gabinete no encontrado.');
        }

        $gabinete->desactivar();
        $this->gabineteRepo->guardar($gabinete);

        return new DesactivarGabineteOutput;
    }
}
