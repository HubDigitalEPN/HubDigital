<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

/**
 * Caso de uso: añadir una ranura individual a un gabinete existente, opcionalmente con la
 * familia taxonómica que se espera alojar en ella.
 *
 * @see CrearRanuraGabineteInput
 * @see CrearRanuraGabineteOutput
 */
final class CrearRanuraGabineteHandler
{
    /**
     * @param  RanuraGabineteRepository  $ranuraRepo  Genera la identidad de la ranura y la persiste.
     * @param  GabineteRepository  $gabineteRepo  Verifica que el gabinete destino exista.
     */
    public function __construct(
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    /**
     * Comprueba que el gabinete exista, crea la ranura con su número y familia esperada
     * opcional, la persiste y devuelve sus datos.
     *
     * @throws \DomainException si el gabinete indicado no existe.
     */
    public function handle(CrearRanuraGabineteInput $input): CrearRanuraGabineteOutput
    {
        $gabineteId = GabineteId::desde($input->gabineteId);

        if ($this->gabineteRepo->buscarPorId($gabineteId) === null) {
            throw new \DomainException("Gabinete '{$input->gabineteId}' no encontrado.");
        }

        $id = $this->ranuraRepo->nextIdentity();

        $ranura = RanuraGabinete::crear(
            id: $id,
            gabineteId: $gabineteId,
            numeroRanura: $input->numeroRanura,
            familiaTaxonomicaEsperadaId: $input->familiaTaxonomicaEsperadaId,
        );

        $this->ranuraRepo->guardar($ranura);

        return new CrearRanuraGabineteOutput(
            id: (string) $ranura->id(),
            gabineteId: (string) $ranura->gabineteId(),
            numeroRanura: $ranura->numeroRanura(),
            familiaTaxonomicaEsperadaId: $ranura->familiaTaxonomicaEsperadaId(),
            activa: $ranura->activa(),
        );
    }
}
