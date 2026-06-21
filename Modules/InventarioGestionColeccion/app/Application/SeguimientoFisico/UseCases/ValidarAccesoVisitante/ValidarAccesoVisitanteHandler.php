<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ValidarAccesoVisitante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

/**
 * Valida el acceso de un visitante al aterrizar el enlace firmado del QR.
 *
 * La firma y la expiración temporal las verifica el middleware `signed` de Laravel;
 * aquí solo se comprueba la regla de versión: el QR es válido únicamente si su
 * `version` coincide con la versión de acceso vigente del visitante. Regenerar el
 * acceso incrementa esa versión y deja obsoletos los QR anteriores.
 */
final class ValidarAccesoVisitanteHandler
{
    public function __construct(
        private readonly VisitanteRepositoryInterface $visitanteRepo,
    ) {}

    public function handle(ValidarAccesoVisitanteInput $input): ValidarAccesoVisitanteOutput
    {
        if (! VisitanteId::esValido($input->visitanteId)) {
            return new ValidarAccesoVisitanteOutput(valido: false);
        }

        $visitante = $this->visitanteRepo->buscarPorId(VisitanteId::desde($input->visitanteId));

        if ($visitante === null || $visitante->versionAcceso() !== $input->version) {
            return new ValidarAccesoVisitanteOutput(valido: false);
        }

        return new ValidarAccesoVisitanteOutput(
            valido: true,
            visitanteId: (string) $visitante->id(),
            nombre: $visitante->nombre(),
        );
    }
}
