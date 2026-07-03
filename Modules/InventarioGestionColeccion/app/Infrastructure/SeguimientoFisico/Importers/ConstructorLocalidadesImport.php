<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;

/**
 * Construye la jerarquía de localidades durante el import del Excel, en espejo
 * de `ConstructorTaxonomiaImport`.
 *
 * La celda de localidad viene separada por comas en orden geográfico
 * ("País, Provincia, Cantón, Sitio"). El mapper ya la descompone en los campos
 * Darwin Core `country`/`stateProvince`/`municipality`/`localityName`; aquí se
 * toman esos campos y se crean/reutilizan `Localidad` encadenadas vía
 * `padre_id`:
 *
 *   country      → Pais
 *   stateProvince → Provincia
 *   municipality → Sector
 *   localityName → Sitio
 *
 * Devuelve el `LocalidadId` de la hoja (el nivel más específico con dato) para
 * que el espécimen se enganche ahí. Cada nivel presente cuelga del ancestro
 * presente más cercano, de modo que "Ecuador, Yasuní" produce Pais→Sitio.
 *
 * Devuelve `null` cuando NO hay una jerarquía separable por comas (un solo
 * nombre sin comas → sin campos DwC): esos casos quedan sin `localidad_id` y
 * caen en la bandeja "Localidades por confirmar", donde se marcan como nombre
 * único para que el curador los resuelva a mano.
 *
 * Cache por instancia (una corrida del importer). Clave:
 * "padreId|rango|nombre" — el padre desambigua homónimos en ramas distintas
 * ("Bellavista" en dos provincias son localidades diferentes).
 */
final class ConstructorLocalidadesImport
{
    /** Orden de los niveles del más alto al más bajo, con su campo DwC de origen. */
    private const NIVELES = [
        ['campo' => 'country', 'rango' => RangoLocalidad::Pais],
        ['campo' => 'stateProvince', 'rango' => RangoLocalidad::Provincia],
        ['campo' => 'municipality', 'rango' => RangoLocalidad::Sector],
        ['campo' => 'localityName', 'rango' => RangoLocalidad::Sitio],
    ];

    /** @var array<string, LocalidadId> Cache padreId+rango+nombre → LocalidadId. */
    private array $cache = [];

    private bool $cachePrecargado = false;

    public function __construct(
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    /**
     * Pre-carga TODAS las localidades existentes en memoria con una sola
     * consulta. Sin esto, cada fila dispararía hasta 4 SELECTs (uno por nivel)
     * contra la BD remota.
     */
    public function precargarLocalidadesExistentes(): void
    {
        if ($this->cachePrecargado) {
            return;
        }
        foreach ($this->localidadRepo->buscarTodos() as $localidad) {
            $this->cache[$this->clave(
                $localidad->padreId(),
                $localidad->rango(),
                $localidad->nombreCanonico(),
            )] = $localidad->id();
        }
        $this->cachePrecargado = true;
    }

    /**
     * Resuelve la jerarquía de la fila y devuelve el `LocalidadId` de la hoja al
     * que se debe enganchar el espécimen. Devuelve `null` si la fila no trae una
     * localidad descomponible por comas (sin campos DwC).
     */
    public function resolverDeMapeada(FilaMapeada $fila): ?LocalidadId
    {
        $padreId = null;
        $hojaId = null;

        foreach (self::NIVELES as $nivel) {
            $nombre = $this->limpiar($fila->{$nivel['campo']});
            if ($nombre === null) {
                continue;
            }
            $localidadId = $this->resolverNivel($nivel['rango'], $nombre, $padreId);
            $padreId = $localidadId;
            $hojaId = $localidadId;
        }

        return $hojaId;
    }

    private function resolverNivel(RangoLocalidad $rango, string $nombre, ?LocalidadId $padreId): LocalidadId
    {
        $clave = $this->clave($padreId, $rango, $nombre);
        if (isset($this->cache[$clave])) {
            return $this->cache[$clave];
        }

        // Cache miss: si ya precargamos, sabemos que no existe → INSERT directo
        // sin SELECT redundante.
        if (! $this->cachePrecargado) {
            $existente = $this->localidadRepo->buscarPorNombreYRango($nombre, $rango);
            if ($existente !== null && $this->mismoPadre($existente->padreId(), $padreId)) {
                $this->cache[$clave] = $existente->id();

                return $existente->id();
            }
        }

        $nueva = Localidad::crear(
            id: $this->localidadRepo->nextIdentity(),
            nombreCanonico: $nombre,
            rango: $rango->value,
            padreId: $padreId !== null ? (string) $padreId : null,
        );
        $this->localidadRepo->guardar($nueva);
        $this->cache[$clave] = $nueva->id();

        return $nueva->id();
    }

    private function clave(?LocalidadId $padreId, RangoLocalidad $rango, string $nombre): string
    {
        return ($padreId !== null ? (string) $padreId : '').'|'.$rango->value.'|'.mb_strtolower($nombre);
    }

    private function mismoPadre(?LocalidadId $a, ?LocalidadId $b): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }

        return (string) $a === (string) $b;
    }

    private function limpiar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);
        if ($trim === '' || mb_strtolower($trim) === 'na' || $trim === '-') {
            return null;
        }

        return $trim;
    }
}
