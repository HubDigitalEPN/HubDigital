<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use App\Support\CatalogoTerritoriosDesglose;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\DesgloseLocalidad;

/** El plan no escribe; la aplicación es atómica y conserva identidad, custodia y divulgación. */
final class ActualizacionCatalogo
{
    private array $taxonesPorClave = [];
    private array $taxonesPorId = [];
    private array $linajesPropuestos = [];
    private array $plantilla = [];
    public function __construct(private readonly LectorActualizacionCatalogo $lector, private readonly FilaCatalogoMapper $mapper, private readonly CatalogoTerritoriosDesglose $territorios) {}

    public static function identidad(string $valor): string
    {
        return preg_replace('/^MEPN[-:]INV[-:]/i', 'MEPN-INV-', trim($valor));
    }

    private function identidadSql(string $campo): string
    {
        return "regexp_replace(btrim(coalesce({$campo}, '')), '^MEPN[-:]INV[-:]', 'MEPN-INV-', 'i')";
    }

    private function clavesFila(array $datos): array
    {
        return array_values(array_unique(array_filter(array_map(self::identidad(...), [$datos['occurrence_id'] ?? '', $datos['catalog_number'] ?? '']), static fn ($v) => $v !== '')));
    }

    private function coincidenciasFila(array $datos, array $destinos): array
    {
        $coincidencias = [];
        foreach ($this->clavesFila($datos) as $clave) $coincidencias += $destinos[$clave] ?? [];
        return $coincidencias;
    }

    private function lotes(string $ruta, string $extension, string $separador): \Generator
    {
        $lote = [];
        $bytes = 0;
        foreach ($this->lector->filas($ruta, $extension, $separador, ConfiguracionPlantillaCatalogo::alias($this->plantilla)) as $fila => $registro) {
            ConfiguracionPlantillaCatalogo::validarFila($registro['datos'], $fila, $this->plantilla);
            $tamano = strlen(json_encode($registro, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if ($lote !== [] && $bytes + $tamano > 2 * 1024 * 1024) { yield $lote; $lote = []; $bytes = 0; }
            $lote[$fila] = $registro;
            $bytes += $tamano;
            if (count($lote) === 200) { yield $lote; $lote = []; $bytes = 0; }
        }
        if ($lote !== []) yield $lote;
    }

    private function destinos(array $lote): array
    {
        $claves = [];
        foreach ($lote as $r) {
            $d = $r['datos'];
            array_push($claves, ...$this->clavesFila($d));
        }
        $salida = [];
        $filas = DB::table('taxonomia.especimenes')->where(function ($q) use ($claves): void {
            foreach (['occurrence_id', 'codigo_catalogo', 'catalog_number'] as $campo) $q->orWhereIn(DB::raw($this->identidadSql($campo)), $claves);
        })->get();
        foreach ($filas as $fila) {
            foreach (['occurrence_id', 'codigo_catalogo', 'catalog_number'] as $campo) {
                $clave = self::identidad($fila->{$campo} ?? '');
                if ($clave !== '') $salida[$clave][$fila->id] = $fila;
            }
        }
        return $salida;
    }

    private function taxonomia(array $datos): array
    {
        $epiteto = trim($datos['specific_epithet'] ?? '');
        $provisional = $epiteto !== '' && !preg_match('/^[a-z][a-z-]+$/D', $epiteto);
        if ($provisional || ($epiteto === '' && trim($datos['genus'] ?? '') !== '')) {
            unset($datos['specific_epithet']);
            $datos['taxon_rank'] = 'genus';
            $provisional = true;
        }
        return [$datos, $provisional];
    }

    private function unirNotas(?string ...$notas): ?string
    {
        $texto = '';
        foreach ($notas as $nota) {
            $nota = trim($nota ?? '');
            if ($nota !== '' && !str_contains($texto, $nota)) $texto .= ($texto === '' ? '' : "\n").$nota;
        }
        return $texto === '' ? null : $texto;
    }

    private function preparar(array $registro, ?object $anterior, int $fila, string $modo, array $columnas): array
    {
        $datos = $registro['datos'];
        $codigo = self::identidad($datos['occurrence_id'] ?? $datos['catalog_number'] ?? '');
        if ($codigo === '') throw new DomainException("Fila {$fila}: occurrenceID o catalogNumber es obligatorio para identificar el registro.");
        if (mb_strlen($codigo) > 100) throw new DomainException("Fila {$fila}: el identificador supera los 100 caracteres permitidos.");
        $mapeada = $this->mapper->mapear($datos);
        if ($mapeada->individualCount !== null && $mapeada->individualCount < 0) throw new DomainException("Fila {$fila}: individualCount no puede ser negativo.");
        [$taxonomia, $provisional] = $this->taxonomia($datos);
        $cambios = [];
        $extras = [];
        foreach ($this->plantilla['columnas'] as $columnaExtra) {
            if (!($columnaExtra['personalizada'] ?? false)) continue;
            $claveExtra = $columnaExtra['campo'];
            if (!isset($datos[$claveExtra]) || trim((string) $datos[$claveExtra]) === '') continue;
            $valorExtra = trim((string) $datos[$claveExtra]);
            if (mb_strlen($valorExtra) > 5000) throw new DomainException("Fila {$fila}: el campo «{$columnaExtra['cabecera']}» supera los 5000 caracteres.");
            $previoExtra = $anterior ? DB::table('taxonomia.valores_columnas_especimen')->where(['especimen_id' => $anterior->id, 'campo' => $claveExtra])->value('valor') : null;
            if ($modo === 'completar' && $previoExtra !== null && $previoExtra !== '') continue;
            if ($previoExtra !== $valorExtra) $extras[$claveExtra] = $valorExtra;
        }
        $adicionales = [];
        foreach (get_object_vars($mapeada) as $propiedad => $valor) {
            if (in_array($propiedad, ['codigoCatalogo', 'occurrenceId', 'warnings'], true) || $valor === null || $valor === '') continue;
            $columna = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $propiedad));
            if (!isset($columnas[$columna])) continue;
            $maximo = $columnas[$columna]['character_maximum_length'];
            if ($maximo !== null && mb_strlen((string)$valor) > $maximo) throw new DomainException("Fila {$fila}: «{$columna}» supera los {$maximo} caracteres permitidos. El original se conserva; corrige la celda antes de importar.");
            if ($anterior && $modo === 'completar' && $anterior->{$columna} !== null && $anterior->{$columna} !== '') continue;
            $cambios[$columna] = $valor;
        }
        $notas = $this->unirNotas($anterior->specimen_notes ?? null, $datos['specimen_notes'] ?? null, isset($datos['comment']) ? 'comment: '.$datos['comment'] : null);
        if ($notas !== null) $cambios['specimen_notes'] = $notas;
        $generales = $this->unirNotas($anterior->notes_general ?? null, $datos['notes_general'] ?? null);
        if ($generales !== null) $cambios['notes_general'] = $generales;
        $notasTaxonomia = $this->unirNotas($anterior->taxonomic_notes ?? null, $datos['taxonomic_notes'] ?? null,
            $provisional ? 'Identificación provisional original: '.($datos['scientific_name'] ?? $datos['specific_epithet'] ?? $datos['genus']).'. Conservada a nivel de género hasta revisión curatorial.' : null);
        if ($notasTaxonomia !== null) $cambios['taxonomic_notes'] = $notasTaxonomia;
        // Sólo corrige los marcadores vacíos del catálogo anterior, nunca una ficha identificada.
        $marcador = $anterior && $anterior->taxon_id === null && $anterior->fecha_colecta === null && $anterior->individual_count === null
            && ($anterior->localidad === null || $anterior->localidad === '') && (string)($anterior->old_code ?? '') === (string)($fila - 1);
        if ($marcador) { $cambios['old_code'] = $mapeada->oldCode; $cambios['muestra_id'] = null; }
        if (!$anterior || $modo === 'actualizar' || ($anterior->localidad === null || $anterior->localidad === '')) {
            $region = DesgloseLocalidad::clave($mapeada->country ?? '') === 'ecuador' ? $this->territorios->paraProvincia($mapeada->stateProvince ?? '') : [];
            $municipio = $region[DesgloseLocalidad::clave($mapeada->municipality ?? '')] ?? null;
            $localidad = DesgloseLocalidad::desde($mapeada->localidadVerbatim ?? $mapeada->localidad, $municipio, territorios: $region, contexto: [$mapeada->country, $mapeada->stateProvince]);
            if ($mapeada->localidad !== '') {
                $cambios['localidad'] = $localidad['localidad'] ?? '';
                $cambios['localidad2'] = $localidad['localidad2'];
                $cambios['localidad3'] = $localidad['localidad3'];
                $cambios['localidad_desglosada'] = !$localidad['requiere_revision'];
            }
        }
        foreach ($datos as $clave => $valor) {
            if (!isset($columnas[$clave]) && !in_array($clave, ['kingdom','phylum','class','order','suborder','family','subfamily','tribe','genus','specific_epithet','scientific_name','taxon_rank','comment'], true)) $adicionales[$clave] = true;
        }
        $cambios = array_filter($cambios, static function ($valor, $clave) use ($anterior): bool {
            if (!$anterior) return true;
            $actual = $anterior->{$clave};
            if ($valor === null) return $actual !== null;
            if (is_bool($valor)) return (bool)$actual !== $valor;
            if (is_float($valor)) return $actual === null || abs((float)$actual - $valor) > 0.0000001;
            return (string)$actual !== (string)$valor;
        }, ARRAY_FILTER_USE_BOTH);
        $tieneTaxonomia = count(array_intersect_key($taxonomia, array_flip(['kingdom','phylum','class','order','suborder','family','subfamily','tribe','genus','specific_epithet']))) > 0;
        $aplicarTaxonomia = $tieneTaxonomia && (!$anterior || $anterior->taxon_id === null || $modo === 'actualizar');
        return compact('codigo', 'cambios', 'taxonomia', 'provisional', 'adicionales', 'aplicarTaxonomia', 'extras') + ['avisos' => $mapeada->warnings];
    }

    private function columnas(): array
    {
        return DB::table('information_schema.columns')->where('table_schema', 'taxonomia')->where('table_name', 'especimenes')->get()->keyBy('column_name')->map(static fn ($v) => (array)$v)->all();
    }

    private function cargarTaxonomia(): string
    {
        $this->taxonesPorClave = $this->taxonesPorId = $this->linajesPropuestos = [];
        $hash = hash_init('sha256');
        foreach (DB::table('taxonomia.taxones')->orderBy('id')->get(['id','nombre_cientifico','rango','padre_id']) as $taxon) {
            $this->taxonesPorId[$taxon->id] = $taxon;
            $this->taxonesPorClave[$taxon->rango.'|'.$taxon->nombre_cientifico][] = $taxon;
            hash_update($hash, json_encode($taxon, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }
        return hash_final($hash);
    }

    private function validarLinaje(array $datos, int $fila): void
    {
        $datos = ReconciliacionCientificaQa6::taxonomia($datos);
        $ancestros = [];
        foreach (['kingdom' => 'reino', 'phylum' => 'phylum', 'class' => 'clase', 'order' => 'orden', 'suborder' => 'suborden', 'family' => 'familia', 'subfamily' => 'subfamilia', 'tribe' => 'tribu', 'genus' => 'genero', 'specific_epithet' => 'especie'] as $campo => $rango) {
            $nombre = trim($datos[$campo] ?? '');
            if ($nombre === '' || in_array(mb_strtolower($nombre), ['na','-'], true)) continue;
            if ($campo === 'specific_epithet') $nombre = trim(($datos['genus'] ?? '').' '.$nombre);
            $coincidencias = $this->taxonesPorClave[$rango.'|'.$nombre] ?? [];
            if (count($coincidencias) > 1) throw new DomainException("Fila {$fila}: el taxón «{$nombre}» tiene varias fichas. Revisa la taxonomía del catálogo antes de importar.");
            $actual = $coincidencias[0] ?? null;
            $clave = $rango.'|'.$nombre;
            foreach ($ancestros as $nivel => $nombreAncestro) {
                $propuesto = $this->linajesPropuestos[$clave][$nivel] ?? null;
                if ($propuesto !== null && $propuesto !== $nombreAncestro) throw new DomainException("Fila {$fila}: el taxón «{$nombre}» tiene clasificaciones incompatibles dentro de la matriz.");
                $this->linajesPropuestos[$clave][$nivel] = $nombreAncestro;
            }
            if ($actual) {
                $padre = $actual->padre_id;
                $visitados = [];
                while ($padre !== null && !isset($visitados[$padre])) {
                    $visitados[$padre] = true;
                    $taxonPadre = $this->taxonesPorId[$padre] ?? null;
                    if (!$taxonPadre) break;
                    if (isset($ancestros[$taxonPadre->rango]) && $ancestros[$taxonPadre->rango] !== $taxonPadre->nombre_cientifico) {
                        $nombreAnterior = $ancestros[$taxonPadre->rango];
                        throw new DomainException("Fila {$fila}: «{$nombre}» no pertenece a «{$nombreAnterior}» en el catálogo actual. Revisa la clasificación antes de importar.");
                    }
                    $padre = $taxonPadre->padre_id;
                }
            }
            $ancestros[$rango] = $nombre;
        }
    }

    /** Añade niveles ausentes sin sustituir un ancestro de otro nombre. */
    private function completarLinaje(array $datos): array
    {
        $datos = ReconciliacionCientificaQa6::taxonomia($datos);
        $padre = null;
        $ajustes = [];
        foreach (['kingdom' => 'reino', 'phylum' => 'phylum', 'class' => 'clase', 'order' => 'orden', 'suborder' => 'suborden', 'family' => 'familia', 'subfamily' => 'subfamilia', 'tribe' => 'tribu', 'genus' => 'genero', 'specific_epithet' => 'especie'] as $campo => $rango) {
            $nombre = trim($datos[$campo] ?? '');
            if ($nombre === '' || in_array(mb_strtolower($nombre), ['na','-'], true)) continue;
            if ($campo === 'specific_epithet') $nombre = trim(($datos['genus'] ?? '').' '.$nombre);
            $actual = DB::table('taxonomia.taxones')->where('rango', $rango)->where('nombre_cientifico', $nombre)->first();
            if (!$actual) continue;
            if ($padre && $actual->padre_id !== $padre->id) {
                $cursor = $padre;
                $visitados = [];
                while ($cursor && $cursor->id !== $actual->padre_id && !isset($visitados[$cursor->id])) {
                    $visitados[$cursor->id] = true;
                    $cursor = $cursor->padre_id ? DB::table('taxonomia.taxones')->where('id', $cursor->padre_id)->first() : null;
                }
                if ($actual->padre_id === null || $cursor?->id === $actual->padre_id) {
                    $ajustes[] = ['taxon_id' => $actual->id, 'nombre' => $nombre, 'padre_anterior' => $actual->padre_id, 'padre_nuevo' => $padre->id];
                    DB::table('taxonomia.taxones')->where('id', $actual->id)->update(['padre_id' => $padre->id, 'updated_at' => now()]);
                    $actual->padre_id = $padre->id;
                }
            }
            $padre = $actual;
        }
        return $ajustes;
    }

    public function analizar(string $ruta, string $extension, string $separador = ',', string $modo = 'completar'): array
    {
        if (!in_array($modo, ['completar', 'actualizar'], true)) throw new DomainException('Selecciona una forma de actualización válida.');
        $columnas = $this->columnas();
        $resumen = ['filas' => 0, 'nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'provisionales' => 0, 'avisos' => [], 'muestra' => [], 'columnas_adicionales' => []];
        $vistas = [];
        $hash = hash_init('sha256');
        $this->plantilla = app(ConfiguracionPlantillaCatalogo::class)->leer();
        hash_update($hash, hash_file('sha256', $ruta).':'.$modo.':'.$separador.':'.$this->cargarTaxonomia().':'.ConfiguracionPlantillaCatalogo::huella($this->plantilla));
        foreach ($this->lotes($ruta, $extension, $separador) as $lote) {
            $destinos = $this->destinos($lote);
            foreach ($lote as $fila => $registro) {
                $codigo = self::identidad($registro['datos']['occurrence_id'] ?? $registro['datos']['catalog_number'] ?? '');
                foreach ($this->clavesFila($registro['datos']) as $clave) {
                    if (isset($vistas[$clave])) throw new DomainException("Fila {$fila}: el identificador «{$clave}» ya aparece en la fila {$vistas[$clave]}. Revisa las dos filas.");
                    $vistas[$clave] = $fila;
                }
                $coincidencias = $this->coincidenciasFila($registro['datos'], $destinos);
                if (count($coincidencias) > 1) throw new DomainException("Fila {$fila}: «{$codigo}» coincide con varias fichas. Resuelve la identidad en el catálogo antes de cargar la actualización.");
                $anterior = $coincidencias === [] ? null : reset($coincidencias);
                if ($anterior) {
                    $claveFicha = 'uuid:'.$anterior->id;
                    if (isset($vistas[$claveFicha])) throw new DomainException("Fila {$fila}: la misma ficha ya aparece en la fila {$vistas[$claveFicha]} con otro identificador. Revisa las dos filas.");
                    $vistas[$claveFicha] = $fila;
                }
                $plan = $this->preparar($registro, $anterior, $fila, $modo, $columnas);
                $this->validarLinaje($plan['taxonomia'], $fila);
                $accion = !$anterior ? 'nuevos' : (($plan['cambios'] !== [] || $plan['extras'] !== [] || $plan['aplicarTaxonomia']) ? 'actualizados' : 'sin_cambios');
                $resumen['filas']++;
                $resumen[$accion]++;
                if ($plan['provisional']) $resumen['provisionales']++;
                foreach ($plan['avisos'] as $aviso) $resumen['avisos'][$aviso] = ($resumen['avisos'][$aviso] ?? 0) + 1;
                $resumen['columnas_adicionales'] += $plan['adicionales'];
                if (count($resumen['muestra']) < 20) $resumen['muestra'][] = ['fila' => $fila, 'codigo' => $codigo, 'accion' => $accion, 'campos' => array_keys($plan['cambios']), 'provisional' => $plan['provisional']];
                hash_update($hash, json_encode([$fila, $codigo, $anterior?->id, $anterior?->updated_at, $plan], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            }
        }
        if ($resumen['filas'] === 0) throw new DomainException('El archivo sólo contiene encabezados o filas vacías de plantilla.');
        $resumen['columnas_adicionales'] = array_keys($resumen['columnas_adicionales']);
        $resumen['huella'] = hash_final($hash);
        return $resumen;
    }

    public function aplicar(string $importacionId, string $ruta): array
    {
        return DB::transaction(function () use ($importacionId, $ruta): array {
            DB::statement("SET LOCAL lock_timeout = '10s'");
            DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'catalogo')->lockForUpdate()->first();
            DB::statement('LOCK TABLE taxonomia.taxones, taxonomia.especimenes IN SHARE ROW EXCLUSIVE MODE');
            $carga = DB::table('taxonomia.importaciones_catalogo')->where('id', $importacionId)->lockForUpdate()->first();
            if (!$carga || !hash_equals($carga->fuente_sha256, hash_file('sha256', $ruta))) throw new DomainException('El archivo no coincide con el original validado.');
            if ($carga->estado === 'completada') return json_decode($carga->resumen, true, 512, JSON_THROW_ON_ERROR);
            $opciones = json_decode($carga->resumen, true, 512, JSON_THROW_ON_ERROR);
            $modo = $opciones['modo'];
            $plan = $this->analizar($ruta, $carga->extension, $carga->delimitador, $modo);
            if (!hash_equals($carga->plan_sha256, $plan['huella'])) throw new DomainException('El catálogo cambió después de la validación. Vuelve a validar el archivo para revisar los cambios actuales.');
            $columnas = $this->columnas();
            $taxonomia = new ConstructorTaxonomiaImport(app(TaxonRepositoryInterface::class));
            $taxonomia->precargarTaxonomiaExistente();
            foreach ($this->lotes($ruta, $carga->extension, $carga->delimitador) as $lote) {
                $destinos = $this->destinos($lote);
                $auditoria = [];
                foreach ($lote as $fila => $registro) {
                    $codigo = self::identidad($registro['datos']['occurrence_id'] ?? $registro['datos']['catalog_number'] ?? '');
                    $coincidencias = $this->coincidenciasFila($registro['datos'], $destinos);
                    $anterior = $coincidencias === [] ? null : reset($coincidencias);
                    $filaPlan = $this->preparar($registro, $anterior, $fila, $modo, $columnas);
                    $cambios = $filaPlan['cambios'];
                    $ajustesLinaje = [];
                    if ($filaPlan['aplicarTaxonomia']) {
                        $taxon = $taxonomia->resolverDeFila($filaPlan['taxonomia']);
                        $ajustesLinaje = $this->completarLinaje($filaPlan['taxonomia']);
                        if ($taxon !== null) $cambios['taxon_id'] = (string)$taxon;
                    }
                    $accion = !$anterior ? 'nuevo' : ($cambios === [] && $filaPlan['extras'] === [] ? 'sin_cambios' : 'actualizado');
                    $id = $anterior?->id ?? (string)Str::uuid();
                    if ($cambios !== [] || $filaPlan['extras'] !== [] || !$anterior) {
                        $cambios['updated_at'] = now();
                        $cambios['estado_revision'] = 'pendiente';
                        $cambios['motivo_revision'] = $filaPlan['provisional'] ? 'Identificación provisional conservada a nivel de género; revisar el original de la importación.' : 'Actualización desde matriz del curador; revisión pendiente.';
                        if ($anterior) DB::table('taxonomia.especimenes')->where('id', $id)->update($cambios);
                        else DB::table('taxonomia.especimenes')->insert($cambios + ['id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo, 'estado' => 'disponible', 'disposition' => 'in_collection', 'created_at' => now()]);
                    }
                    foreach ($filaPlan['extras'] as $campoExtra => $valorExtra) {
                        $antesExtra = DB::table('taxonomia.valores_columnas_especimen')->where(['especimen_id' => $id, 'campo' => $campoExtra])->value('valor');
                        DB::table('taxonomia.valores_columnas_especimen')->updateOrInsert(['especimen_id' => $id, 'campo' => $campoExtra], ['valor' => $valorExtra, 'actualizado_por' => $carga->creado_por_id, 'updated_at' => now()]);
                        DB::table('taxonomia.historial_columnas_especimen')->insert(['especimen_id' => $id, 'campo' => $campoExtra, 'anterior' => $antesExtra, 'nuevo' => $valorExtra, 'actualizado_por' => $carga->creado_por_id, 'created_at' => now()]);
                    }
                    $posterior = DB::table('taxonomia.especimenes')->where('id', $id)->first();
                    if ($ajustesLinaje !== []) $posterior->_linajes_completados = $ajustesLinaje;
                    $auditoria[] = ['id' => (string)Str::uuid(), 'importacion_id' => $importacionId, 'fila' => $fila, 'especimen_id' => $id, 'codigo' => $codigo, 'accion' => $accion,
                        'original' => json_encode($registro['original'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'anterior' => $anterior ? json_encode($anterior, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
                        'posterior' => json_encode($posterior, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
                }
                DB::table('taxonomia.importacion_filas_catalogo')->insert($auditoria);
            }
            DB::table('taxonomia.importaciones_catalogo')->where('id', $importacionId)->update(['estado' => 'completada', 'resumen' => json_encode($plan + ['modo' => $modo], JSON_THROW_ON_ERROR), 'error' => null, 'updated_at' => now()]);
            return $plan;
        });
    }
}
