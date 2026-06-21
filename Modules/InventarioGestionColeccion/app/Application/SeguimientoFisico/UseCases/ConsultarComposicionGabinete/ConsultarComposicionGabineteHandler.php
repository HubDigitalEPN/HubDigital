<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarComposicionGabinete;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

/**
 * Resuelve qué subfamilias y géneros están presentes en un gabinete, agregando
 * la clasificación taxonómica cacheada en las cajas que ocupan sus ranuras.
 * Es la lectura que alimenta el resumen del nivel "gabinete" del mapa.
 *
 * @see ConsultarComposicionGabineteInput
 * @see ConsultarComposicionGabineteOutput
 */
final class ConsultarComposicionGabineteHandler
{
    /**
     * @param  RanuraGabineteRepository  $ranuraRepo  Lista las ranuras del gabinete.
     * @param  CajaRepository  $cajaRepo  Recupera la caja que ocupa cada ranura para leer su clasificación.
     */
    public function __construct(
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly CajaRepository $cajaRepo,
    ) {}

    /**
     * Recorre las ranuras del gabinete, agrega las subfamilias y géneros distintos de las
     * cajas que las ocupan y los devuelve ordenados alfabéticamente.
     */
    public function handle(ConsultarComposicionGabineteInput $input): ConsultarComposicionGabineteOutput
    {
        $gabineteId = GabineteId::desde($input->gabineteId);
        $ranuras = $this->ranuraRepo->buscarPorGabinete($gabineteId);

        $subfamilias = [];
        $generos = [];

        foreach ($ranuras as $ranura) {
            $caja = $this->cajaOcupante($ranura);
            if ($caja === null) {
                continue;
            }

            $clasificacion = $caja->clasificacionTaxonomica();
            if ($clasificacion === null) {
                continue;
            }

            // Una caja puede albergar varias subfamilias y géneros: se agregan todos.
            foreach ($clasificacion->subfamilias() as $subfamilia) {
                $subfamilias[$subfamilia] = true;
            }
            foreach ($clasificacion->generos() as $genero) {
                $generos[$genero] = true;
            }
        }

        $subfamilias = array_keys($subfamilias);
        $generos = array_keys($generos);
        sort($subfamilias);
        sort($generos);

        return new ConsultarComposicionGabineteOutput(
            subfamilias: $subfamilias,
            generos: $generos,
        );
    }

    private function cajaOcupante(RanuraGabinete $ranura)
    {
        $cajaId = $ranura->cajaActualId();

        return $cajaId !== null ? $this->cajaRepo->buscarPorId($cajaId) : null;
    }
}
