<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverEntidadDepositante;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEntidadDepositante;

/**
 * Encuentra —o crea— la entidad depositante a la que pertenece un lote que ingresa.
 *
 * Existe porque `taxonomia.especimenes.entidad_depositante_id` tiene su clave foránea
 * desde el principio y nunca se llenaba: todo el material depositado entraba sin
 * depositante, y averiguar de quién era exigía volver al módulo de recepciones.
 *
 * **Qué es la entidad**: la contraparte del depósito. Si el depositante declara una
 * institución, la entidad es la institución y la persona queda como contacto; si no
 * declara ninguna, la entidad es la persona. Por eso el vocabulario del inventario ya
 * distinguía `institucion` de `persona`.
 *
 * Varios investigadores de una misma institución comparten entidad, que es lo que se
 * busca: agrupa la procedencia. Saber quién tramitó cada lote en concreto sigue siendo
 * posible por la solicitud, que el espécimen también conoce.
 *
 * A diferencia de {@see RegistrarEntidadDepositanteHandler},
 * que rechaza los nombres repetidos porque el curador los está dando de alta a mano,
 * aquí un nombre repetido es el caso normal y esperado: significa que esa institución ya
 * depositó antes.
 */
final class ResolverEntidadDepositanteHandler
{
    public function __construct(
        private readonly EntidadDepositanteRepositoryInterface $entidadRepo,
    ) {}

    public function handle(ResolverEntidadDepositanteInput $input): ResolverEntidadDepositanteOutput
    {
        [$nombre, $tipo] = $this->identidad($input);

        $existente = $this->entidadRepo->buscarPorNombre($nombre);

        if ($existente !== null) {
            return new ResolverEntidadDepositanteOutput(
                entidadId: (string) $existente->id(),
                nombre: $existente->nombre(),
                tipo: $existente->tipo()?->value ?? $tipo->value,
                creada: false,
            );
        }

        $entidad = EntidadDepositante::crear(
            id: $this->entidadRepo->nextIdentity(),
            nombre: $nombre,
            tipo: $tipo->value,
            contacto: $this->contacto($input, $tipo),
        );

        try {
            $this->entidadRepo->guardar($entidad);
        } catch (\Throwable $e) {
            // `nombre` es único. Dos lotes de la misma institución aprobados a la vez
            // pueden llegar aquí a la par y perder la carrera; en ese caso la entidad ya
            // existe y sirve igual, que es lo único que nos importa.
            $ganador = $this->entidadRepo->buscarPorNombre($nombre);

            if ($ganador === null) {
                throw $e;
            }

            return new ResolverEntidadDepositanteOutput(
                entidadId: (string) $ganador->id(),
                nombre: $ganador->nombre(),
                tipo: $ganador->tipo()?->value ?? $tipo->value,
                creada: false,
            );
        }

        return new ResolverEntidadDepositanteOutput(
            entidadId: (string) $entidad->id(),
            nombre: $entidad->nombre(),
            tipo: $tipo->value,
            creada: true,
        );
    }

    /**
     * Nombre y tipo de la entidad: la institución manda; si no hay, la persona.
     *
     * @return array{string, TipoEntidadDepositante}
     */
    private function identidad(ResolverEntidadDepositanteInput $input): array
    {
        $institucion = $this->limpiar($input->institucion);

        if ($institucion !== null) {
            return [$institucion, TipoEntidadDepositante::Institucion];
        }

        $persona = $this->limpiar($input->nombrePersona);

        if ($persona === null) {
            throw new \InvalidArgumentException(
                'No se puede resolver la entidad depositante: el lote no trae ni institución ni nombre de la persona.'
            );
        }

        return [$persona, TipoEntidadDepositante::Persona];
    }

    /**
     * A quién escribir. Cuando la entidad es una institución, el contacto es la persona
     * que tramitó; cuando la entidad ya es la persona, basta su correo.
     */
    private function contacto(ResolverEntidadDepositanteInput $input, TipoEntidadDepositante $tipo): ?string
    {
        $email = $this->limpiar($input->email);
        $persona = $this->limpiar($input->nombrePersona);

        if ($tipo === TipoEntidadDepositante::Persona) {
            return $email;
        }

        if ($persona === null) {
            return $email;
        }

        return $email === null ? $persona : sprintf('%s <%s>', $persona, $email);
    }

    private function limpiar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim($valor);

        return $texto === '' ? null : $texto;
    }
}
