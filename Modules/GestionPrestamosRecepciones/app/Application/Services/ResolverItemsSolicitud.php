<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Services;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\EspecimenNoSolicitableException;
use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoEspecimenesPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;

/**
 * Convierte los ítems crudos de una solicitud en entidades {@see ItemPrestamo},
 * contrastándolos contra el catálogo de especímenes.
 *
 * Compartido por los casos de uso de registrar y actualizar solicitudes: la
 * revalidación contra el catálogo es una comprobación de seguridad — los ítems
 * llegan desde el navegador y son manipulables — y tenerla duplicada en dos
 * handlers es pedir que se desincronicen.
 */
final class ResolverItemsSolicitud
{
    public function __construct(
        private readonly CatalogoEspecimenesPort $catalogo,
    ) {}

    /**
     * El `id` de cada ítem es opcional: al actualizar una solicitud se conserva
     * el existente, y al registrarla se genera uno nuevo.
     *
     * @param  list<array{id?: string, especimen_id: string, cantidad_solicitada: int}>  $items
     * @return list<ItemPrestamo>
     *
     * @throws EspecimenNoSolicitableException Si algún espécimen no está disponible o la cantidad excede lo existente.
     */
    public function resolver(array $items): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (array $item) => (string) $item['especimen_id'],
            $items
        )));

        // Una sola consulta al catálogo para todos los ítems.
        $especimenes = $this->catalogo->obtenerPorIds($ids);

        return array_map(function (array $item) use ($especimenes): ItemPrestamo {
            $especimenId = (string) $item['especimen_id'];
            $cantidad = (int) $item['cantidad_solicitada'];

            $especimen = $especimenes[$especimenId] ?? null;

            // Ausente del resultado = inexistente o ya no disponible: para el
            // investigador ambos casos significan lo mismo.
            if ($especimen === null) {
                throw EspecimenNoSolicitableException::noDisponible($especimenId);
            }

            if ($cantidad > $especimen->individualesDisponibles) {
                throw EspecimenNoSolicitableException::cantidadExcedida(
                    $especimen->codigoCatalogo,
                    $cantidad,
                    $especimen->individualesDisponibles
                );
            }

            return ItemPrestamo::crear(
                id: isset($item['id'])
                    ? ItemPrestamoId::fromString($item['id'])
                    : ItemPrestamoId::generate(),
                especimenId: $especimen->especimenId,
                especimenCodigoExterno: $especimen->codigoCatalogo,
                cantidadSolicitada: $cantidad,
                especimenSnapshot: $especimen->aSnapshot(),
            );
        }, $items);
    }
}
