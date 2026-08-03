<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReemplazarTextoEnEspecimenes;

use DateTimeImmutable;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DetalleEdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\BitacoraEdicionMasivaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\NormalizadorValorCampoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEdicionMasiva;

/**
 * Busca y reemplaza texto dentro de una columna, en las filas seleccionadas.
 *
 * El caso que lo justifica: el Excel original trae el mismo colector o la misma
 * localidad escritos de varias formas, y corregirlos uno a uno sobre 48.000
 * filas no es viable.
 *
 * El cálculo se hace en PHP, fila a fila, y no con `regexp_replace` de Postgres.
 * Dos razones: el resultado que se muestra en la vista previa es exactamente el
 * que se va a escribir (no una aproximación), y el doble en memoria que usan los
 * tests se comporta igual que la base real.
 */
final class ReemplazarTextoEnEspecimenesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly BitacoraEdicionMasivaRepositoryInterface $bitacoraRepo,
        private readonly NormalizadorValorCampoEspecimen $normalizador,
        private readonly TransactionManagerPort $transacciones,
    ) {}

    public function handle(ReemplazarTextoEnEspecimenesInput $input): ReemplazarTextoEnEspecimenesOutput
    {
        if (! in_array($input->campo, RegistroColumnasEspecimen::clavesEditablesDeTexto(), true)) {
            throw new \InvalidArgumentException(
                "Solo se puede buscar y reemplazar en campos de texto; '{$input->campo}' no lo es."
            );
        }

        if (trim($input->buscar) === '') {
            throw new \InvalidArgumentException('Indica el texto que quieres buscar.');
        }

        $ids = array_values(array_unique(array_filter(
            $input->especimenIds,
            fn ($id) => is_string($id) && EspecimenId::esValido($id),
        )));

        if ($ids === []) {
            throw new \InvalidArgumentException('No hay especímenes seleccionados.');
        }

        $ejecutar = function () use ($ids, $input): ReemplazarTextoEnEspecimenesOutput {
            $previos = $this->especimenRepo->valoresDeCampoPorIds($ids, $input->campo);

            $nuevos = [];
            foreach ($previos as $id => $previo) {
                $nuevo = $this->normalizador->reemplazarEn(
                    $previo,
                    $input->buscar,
                    $input->reemplazo,
                    $input->distinguirMayusculas,
                    $input->palabraCompleta,
                );

                if ($nuevo !== $previo) {
                    $nuevos[$id] = $nuevo;
                }
            }

            $sinCoincidencia = count($previos) - count($nuevos);

            if ($nuevos === []) {
                return new ReemplazarTextoEnEspecimenesOutput(0, $sinCoincidencia);
            }

            // Comprobar el desbordamiento ANTES de escribir nada: si una sola
            // fila no cabe en la columna, se aborta el lote entero nombrándola,
            // en vez de dejar media selección aplicada.
            $codigos = $this->codigosDe(array_keys($nuevos));
            foreach ($nuevos as $id => $nuevo) {
                if ($nuevo !== null) {
                    $this->normalizador->verificarTamano($input->campo, $nuevo, $codigos[$id] ?? $id);
                }
            }

            $muestra = [];
            foreach (array_slice(array_keys($nuevos), 0, 20) as $id) {
                $muestra[] = [
                    'codigoCatalogo' => $codigos[$id] ?? $id,
                    'previo' => $previos[$id] ?? null,
                    'nuevo' => $nuevos[$id],
                ];
            }

            if ($input->simular) {
                return new ReemplazarTextoEnEspecimenesOutput(count($nuevos), $sinCoincidencia, $muestra);
            }

            $edicionId = $this->bitacoraRepo->nextIdentity();
            $this->bitacoraRepo->guardar(EdicionMasiva::registrar(
                id: $edicionId,
                tipo: TipoEdicionMasiva::ReemplazarTexto,
                campo: $input->campo,
                // Cada fila acaba con un valor distinto, así que la cabecera no
                // guarda un "valor aplicado" común: guarda qué se buscó y por
                // qué se cambió, que es lo que describe la operación.
                valorAplicado: null,
                totalAfectados: count($nuevos),
                creadoEn: new DateTimeImmutable,
                actorId: $input->actorId,
                actorNombre: $input->actorNombre,
                textoBuscado: $input->buscar,
                textoReemplazo: $input->reemplazo,
            ));

            $detalles = [];
            foreach ($nuevos as $id => $nuevo) {
                $detalles[] = DetalleEdicionMasiva::registrar(
                    id: $this->bitacoraRepo->nextIdentity(),
                    edicionId: $edicionId,
                    especimenId: $id,
                    valorPrevio: $previos[$id] ?? null,
                    valorAplicado: $nuevo,
                );
            }
            $this->bitacoraRepo->guardarDetalles($detalles);

            $this->especimenRepo->fijarCampoPorIdValor($nuevos, $input->campo);

            return new ReemplazarTextoEnEspecimenesOutput(count($nuevos), $sinCoincidencia, $muestra, $edicionId);
        };

        return $input->simular ? $ejecutar() : $this->transacciones->executeTransactional($ejecutar);
    }

    /**
     * @param  string[]  $ids
     * @return array<string, string>
     */
    private function codigosDe(array $ids): array
    {
        $codigos = [];
        foreach ($this->especimenRepo->buscarPorIds($ids) as $especimen) {
            $codigos[(string) $especimen->id()] = $especimen->codigoCatalogo();
        }

        return $codigos;
    }
}
