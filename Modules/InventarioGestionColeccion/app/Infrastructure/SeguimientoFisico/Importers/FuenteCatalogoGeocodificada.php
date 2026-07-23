<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\UbicacionGeocodificada;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;

/**
 * Decorador de {@see FuenteCatalogoIterator} que, cuando una fila trae
 * coordenadas pero le falta la ubicación administrativa, la completa por
 * geocodificación inversa (país, provincia, cantón, localidad) ANTES de que la
 * fila llegue al importador. Así el orchestrator no cambia: solo recibe filas ya
 * enriquecidas.
 *
 * Diseñado para respetar la política de Nominatim en lotes:
 *  - **Deduplica por coordenada redondeada**: cada sitio se consulta una vez por
 *    corrida (muchos especímenes comparten el mismo punto de colecta).
 *  - **Throttle** configurable entre llamadas externas (~1/seg por defecto).
 *  - **Tope opcional** de consultas nuevas (`maxLookups`) para acotar el tiempo
 *    en una petición web; en CLI se pasa null (sin tope).
 *  - **Solo rellena lo que falta**: nunca pisa un valor que ya venía en el Excel.
 */
final class FuenteCatalogoGeocodificada implements FuenteCatalogoIterator
{
    /** @var array<string, ?UbicacionGeocodificada> Memo local por coordenada redondeada. */
    private array $memo = [];

    private int $lookups = 0;

    private int $filasEnriquecidas = 0;

    public function __construct(
        private readonly FuenteCatalogoIterator $fuente,
        private readonly GeocodificadorInversoPort $geo,
        private readonly FilaCatalogoMapper $mapper,
        private readonly int $throttleMs = 1100,
        private readonly ?int $maxLookups = null,
    ) {}

    public function headers(): array
    {
        return $this->fuente->headers();
    }

    /** Consultas externas realizadas (coordenadas únicas). */
    public function lookupsRealizados(): int
    {
        return $this->lookups;
    }

    /** Filas a las que se les completó al menos un campo. */
    public function filasEnriquecidas(): int
    {
        return $this->filasEnriquecidas;
    }

    public function iterar(): \Generator
    {
        foreach ($this->fuente->iterar() as $numFila => $fila) {
            yield $numFila => $this->enriquecer($fila);
        }
    }

    /**
     * @param  array<string, string>  $fila
     * @return array<string, string>
     */
    private function enriquecer(array $fila): array
    {
        $norm = $this->mapper->normalizarClaves($fila);

        $lat = $this->numeroEnRango($norm['decimal_latitude'] ?? null, 90.0);
        $lon = $this->numeroEnRango($norm['decimal_longitude'] ?? null, 180.0);
        if ($lat === null || $lon === null) {
            return $fila;
        }

        // Con coordenadas presentes, la ubicación administrativa (país/provincia/
        // cantón) se toma de las coordenadas y REEMPLAZA lo que trajera el Excel
        // (decisión del usuario). La localidad específica (locality_name) NO se
        // pisa: es el sitio anotado por el colector. Sin coordenadas ya se retornó
        // arriba, así que la ubicación anterior se mantiene tal cual.
        $u = $this->resolver($lat, $lon);
        if ($u === null) {
            return $fila;
        }

        // Preserva la ubicación anterior (la del Excel) en localidad_verbatim, para
        // poder mostrar el "antes" cuando la reemplazamos por la de coordenadas.
        $verbatimActual = $norm['localidad_verbatim'] ?? $norm['verbatim_locality'] ?? null;
        if ($this->vacio($verbatimActual)) {
            $anterior = $this->componerUbicacionAnterior($norm);
            if ($anterior !== null) {
                $fila['localidad_verbatim'] = $anterior;
            }
        }

        $toco = false;
        if ($u->country !== null) {
            $fila['country'] = $u->country;
            $toco = true;
        }
        if ($u->stateProvince !== null) {
            $fila['state_province'] = $u->stateProvince;
            $toco = true;
        }
        if ($u->municipality !== null) {
            $fila['municipality'] = $u->municipality;
            $toco = true;
        }
        // locality_name solo se completa si viene vacío (con el poblado más cercano);
        // nunca reemplaza la localidad específica que anotó el colector.
        if ($this->vacio($norm['locality_name'] ?? null) && $u->poblado !== null) {
            $fila['locality_name'] = $u->poblado;
            $toco = true;
        }

        if ($toco) {
            $this->filasEnriquecidas++;
        }

        return $fila;
    }

    /**
     * Compone la ubicación anterior (la que traía el Excel) como un string legible
     * "país, provincia, cantón, localidad", para preservarla antes de reemplazar
     * los campos administrativos por los obtenidos de las coordenadas.
     *
     * @param  array<string, string|null>  $norm
     */
    private function componerUbicacionAnterior(array $norm): ?string
    {
        $partes = array_filter([
            $norm['country'] ?? null,
            $norm['state_province'] ?? null,
            $norm['municipality'] ?? null,
            $norm['locality_name'] ?? null,
        ], fn ($p) => is_string($p) && trim($p) !== '');

        return $partes === [] ? null : implode(', ', array_map('trim', $partes));
    }

    private function resolver(float $lat, float $lon): ?UbicacionGeocodificada
    {
        $clave = round($lat, 5).','.round($lon, 5);
        if (array_key_exists($clave, $this->memo)) {
            return $this->memo[$clave];
        }

        if ($this->maxLookups !== null && $this->lookups >= $this->maxLookups) {
            return null;
        }

        $u = $this->geo->geocodificar($lat, $lon);
        $this->memo[$clave] = $u;
        $this->lookups++;

        if ($this->throttleMs > 0) {
            usleep($this->throttleMs * 1000);
        }

        return $u;
    }

    private function numeroEnRango(?string $valor, float $limite): ?float
    {
        if ($valor === null) {
            return null;
        }
        $valor = trim($valor);
        if ($valor === '' || ! is_numeric($valor)) {
            return null;
        }
        $n = (float) $valor;

        return ($n >= -$limite && $n <= $limite) ? $n : null;
    }

    private function vacio(?string $valor): bool
    {
        return $valor === null || trim($valor) === '';
    }
}
