# Anexo del módulo taxonómico — Hub Digital (extraído del repositorio)

> Módulo real: **`InventarioGestionColeccion`** (Bounded Context `SeguimientoFisico`).
> Esquemas PostgreSQL: **`taxonomia.*`** (datos científicos) e **`iot.*`** (almacenamiento físico).
> Fecha de corte: rama `claude/modest-mendel-xs5wt7`, último commit 2026-06-24.

---

## 1. Modelo de datos real (ER físico) — reemplaza el modelo conceptual

### 1.1 Mapeo de tu modelo conceptual → realidad del código

| Entidad conceptual | Equivalente real | Tabla(s) | Clase Eloquent / Dominio |
|---|---|---|---|
| **Taxon** | Existe 1:1 | `taxonomia.taxones` | `TaxonEloquentModel` / `Taxon` |
| **Especimen** | Existe 1:1 (≈70 columnas, Darwin Core) | `taxonomia.especimenes` | `EspecimenEloquentModel` / `Especimen` |
| **CodigoQR** | **No existe como entidad única.** Se modela dual: códigos del espécimen + RFID físico de la caja | `taxonomia.especimen_identificadores` **y** `iot.cajas.codigo_rfid` | `EspecimenIdentificadorEloquentModel` / `IdentificadorEspecimen` (+ `Caja.codigoRfid`) |
| **RegistroETL** | **No existe como tabla.** La trazabilidad de ingesta vive como columnas dentro de `especimenes` | `taxonomia.especimenes` → `fila_origen_excel`, `estado_revision`, `motivo_revision`, `acta_recepcion` | Campos/métodos de revisión en `Especimen` |

> Conclusión para tu informe: el ER conceptual de 4 entidades debe redibujarse como un **núcleo `taxonomia`** (taxones ↔ especímenes ↔ identificadores ↔ localidades ↔ muestras ↔ identificaciones) más un **subsistema `iot`** de almacenamiento físico (gabinetes ↔ ranuras ↔ cajas ↔ unit trays ↔ especímenes). El "QR" y el "ETL" no son tablas, son atributos transversales.

### 1.2 Tablas físicas (columnas exactas de las migraciones)

**`taxonomia.taxones`** — `…/database/migrations/2026_05_12_000012_create_taxonomia_taxones_table.php`
```php
$table->uuid('id')->primary();
$table->string('nombre_cientifico', 255);
$table->string('rango', 50);
$table->string('autor', 255);
$table->unsignedSmallInteger('anio_descripcion');
$table->string('estado', 50)->default('activo');
$table->uuid('padre_id')->nullable();                      // jerarquía (self-FK, 2026_05_13_000001)
$table->string('epiteto_infraespecifico', 120)->nullable();// 2026_06_01_000007
$table->timestamps();
$table->unique(['nombre_cientifico', 'rango']);            // unicidad de nomenclatura
```

**`taxonomia.especimenes`** (núcleo, ~70 cols) — base `2026_05_12_000014_*`, extensión Darwin Core `2026_06_01_000008_extend_taxonomia_especimenes_for_p3.php`, endurecimiento ETL `2026_06_01_000011_harden_especimenes_para_import_masivo.php`
```php
$table->uuid('id')->primary();
$table->string('codigo_catalogo', 100);                    // unique relajado en 2026_06_01_000012
$table->string('occurrence_id', 120)->nullable();
$table->string('catalog_number', 120)->nullable();
$table->string('old_code', 120)->nullable();
$table->string('cardex_liquid_collection_code', 120)->nullable();
$table->uuid('taxon_id')->nullable();                      // FK taxones, nullOnDelete
$table->string('taxon_verbatim', 255)->nullable();
$table->uuid('muestra_id')->nullable();                    // FK muestras_colecta
$table->uuid('localidad_id')->nullable();                  // FK localidades
$table->string('localidad', 255)->nullable();
$table->date('fecha_colecta')->nullable();
$table->string('colector', 255)->nullable();
$table->uuid('entidad_depositante_id')->nullable();        // FK entidades_depositantes
$table->string('estado', 50)->default('disponible');
// + Darwin Core: country, state_province, municipality, decimal_latitude/longitude,
//   geodetic_datum, elevation_min_m/max_m, habitat, biome, endemic, sex, life_stage, ...
// + Ingesta/ETL:
$table->string('acta_recepcion', 120)->nullable();
$table->string('estado_revision', 40)->default('pendiente');
$table->text('motivo_revision')->nullable();
$table->unsignedInteger('fila_origen_excel')->nullable();  // índice parcial único → idempotencia ETL
$table->timestamps();
```

**`taxonomia.especimen_identificadores`** (≈ "CodigoQR" del espécimen) — `2026_05_13_000003_*`
```php
$table->uuid('id')->primary();
$table->uuid('especimen_id');                              // FK especimenes, cascadeOnDelete
$table->string('tipo', 80);                                // codigo_catalogo | occurrence_id | old_code | ...
$table->string('valor', 255);
$table->timestamps();
$table->unique(['especimen_id', 'tipo', 'valor']);
$table->index(['tipo', 'valor']);
```

**Tablas de soporte `taxonomia`:** `entidades_depositantes`, `localidades` (jerárquica, self-FK), `muestras_colecta`, `identificaciones` (historial de determinaciones, `es_actual`), `dataset_config` (metadatos GBIF/Darwin Core), `visitantes` (QR de acceso con `version_acceso`).

**Subsistema `iot` (almacenamiento físico):**
- `iot.gabinetes` (codigo, nombre, total_ranuras, activo)
- `iot.ranuras_gabinete` (gabinete_id, numero_ranura, caja_actual_id, familia_taxonomica_esperada_id)
- `iot.cajas` (codigo, es_especial, observacion, `clasificacion_taxonomica` jsonb, estado, **`codigo_rfid` (8 chars, unique)** ← identificador físico)
- `iot.unit_trays` (caja_id, numero, `clasificacion_dominante` jsonb)
- `iot.unit_tray_especimenes` (unit_tray_id, especimen_id; `unique(especimen_id)`) ← puente IoT ↔ taxonomía
- `iot.ubicaciones_caja` (historial: caja_id, ranura_gabinete_id, ingresada_en, retirada_en)

### 1.3 Diagrama ER físico (relaciones reales)
```
taxonomia.taxones ──(padre_id self)──┐
        │ 1                          │
        │ N                          ▼
taxonomia.especimenes ──N──1── taxonomia.localidades (self jerárquica)
        │   │   │   └──N──1── taxonomia.muestras_colecta ──N──1── localidades
        │   │   └──────N──1── taxonomia.entidades_depositantes
        │   ├──1──N── taxonomia.especimen_identificadores   (≈ CodigoQR)
        │   └──1──N── taxonomia.identificaciones ──N──1── taxones
        │ N
        │ 1
iot.unit_tray_especimenes ──N──1── iot.unit_trays ──N──1── iot.cajas (codigo_rfid)
                                                              │ N
                                                              │ 1
iot.gabinetes ──1──N── iot.ranuras_gabinete ──1──N── iot.ubicaciones_caja
```

---

## 2. Anexo de evidencia real — feature de validación taxonómica

**Archivo:** `Modules/InventarioGestionColeccion/tests/Behat/Features/GestionAutonomaSeguridadFisicaInventario/alerta_incongruencia_taxonomica.feature`
**Estado:** etiquetado **`@listo`** → corre en el pipeline de CI (`--tags=@listo --strict`).
**Context:** `…/tests/Behat/Contexts/GestionAutonomaSeguridadFisicaInventario/AlertaIncongruenciaTaxonomicaContext.php` (8 `#[Given]`, 1 `#[When]`, 6 `#[Then]`; repositorios InMemory; handler `RegistrarIngresoCajaHandler`).

```gherkin
# language: es
@listo
Característica: Alerta de orden taxonómico en la disposición de cajas
  Como curador responsable de la colección,
  quiero que el sistema me alerte si una caja queda fuera del orden taxonómico esperado en el gabinete,
  para mantener la colección organizada según la secuencia de familias definida por el curador y, dentro
  de cada familia, alfabéticamente por subfamilia y género, sin bloquear el flujo de trabajo.

  Esquema del escenario: El sistema evalúa si la caja respeta el orden taxonómico al ser insertada en el gabinete
    Dado que el gabinete tiene cajas vecinas organizadas en orden taxonómico
    Y la caja a insertar tiene una clasificación <posicion> respecto a las cajas adyacentes
    Cuando el sistema detecta que la caja fue insertada en una ranura del gabinete
    Entonces <resultado>

    Ejemplos:
      | posicion                                  | resultado                                                                                    |
      | en orden taxonómico correcto              | el ingreso se registra exitosamente sin alertas                                              |
      | fuera del orden alfabético por subfamilia | el ingreso se registra y se genera una alerta suave de "Orden Taxonómico Fuera de Secuencia" |
      | fuera del orden alfabético por género     | el ingreso se registra y se genera una alerta suave de "Orden Taxonómico Fuera de Secuencia" |

  Escenario: El sistema registra con alerta el ingreso de una caja sin unit trays asignados
    Dado que existe una caja sin unit trays asignados
    Cuando el sistema detecta que la caja fue insertada en una ranura del gabinete
    Entonces el ingreso se registra en el gabinete
    Y se genera una alerta de "Familia No Asignada"
    Y la caja queda en estado "Pendiente de Clasificación"

  Escenario: El sistema registra sin alerta el ingreso de una caja especial con observación
    Dado que existe una caja marcada como "Caja Especial" con la observación "Especímenes incautados - origen no determinado"
    Cuando el sistema detecta que la caja fue insertada en una ranura del gabinete
    Entonces el ingreso se registra con su observación
    Y no se genera ninguna alerta de orden taxonómico

  Esquema del escenario: El sistema evalúa el orden por familia según la secuencia definida por el curador
    Dado que el curador definió la secuencia de familias "Formicidae, Coleoptera, Hemiptera"
    Y el gabinete tiene cajas vecinas que siguen esa secuencia de familias
    Y la caja a insertar tiene una familia <posicion_familia> respecto a las cajas adyacentes
    Cuando el sistema detecta que la caja fue insertada en una ranura del gabinete
    Entonces <resultado>

    Ejemplos:
      | posicion_familia                     | resultado                                                                                    |
      | que respeta la secuencia del curador | el ingreso se registra exitosamente sin alertas                                              |
      | que rompe la secuencia del curador   | el ingreso se registra y se genera una alerta suave de "Orden Taxonómico Fuera de Secuencia" |
      | ausente de la secuencia del curador  | el ingreso se registra exitosamente sin alertas                                              |
```

> Nota: existe además `…/GestionRegistrosTaxonomicos/unicidad_nomenclatura.feature` (unicidad estricta de nomenclatura), pero **no** está `@listo` (no corre en CI todavía).

---

## 3. Resultados con métricas (verificadas estáticamente sobre el repo)

> Conteos hechos por análisis estático (grep/find). **No se ejecutó Behat** en este corte (la suite `@listo` usa repositorios InMemory; la BD productiva no se tocó).

### 3.1 Migración / ETL de datos legacy — ⚠️ bloqueante #1
- **Existe la maquinaria ETL completa:** `…/Infrastructure/SeguimientoFisico/Importers/ImportarCatalogoInvertebrados.php` (lectura por `FuenteCatalogoIterator`, mapeo `FilaCatalogoMapper`, construcción de jerarquía `ConstructorTaxonomiaImport`, bulk insert por chunks de 500, idempotencia por `fila_origen_excel`, tolerancia a errores en `erroresFatales`, modo *dry-run*, flags `--desde/--hasta/--chunk`).
- **Cifras reales de una corrida (N legacy / N OK / N inconsistencias): NO están en el repositorio.** No hay log de migración persistido ni seeders con datos reales (los 4 seeders están vacíos salvo 1 usuario de prueba).
- **Único dato verificable:** fixture de prueba `tests/Unit/Fixtures/catalogo_invertebrados_sample.csv` = **6 registros** (14 columnas Darwin Core).
- **Para cerrar el bloqueante:** correr el importador contra el Excel real y capturar los contadores que el propio importador ya produce (filas leídas, insertadas, `erroresFatales`, especímenes en `estado_revision='pendiente'`). Hoy esos números no existen como artefacto en el repo.

### 3.2 Behat — escenarios (estático, no ejecución)
| Módulo | Features | Escenarios | Esquemas | Features `@listo` | Casos en CI (`@listo`) |
|---|---|---|---|---|---|
| InventarioGestionColeccion | 24 | 58 | 7 | 6 | 24 (21 esc. + 3 esq.) |
| GestionPrestamosRecepciones | 16 | 50 | 23 | 6 | 38 (28 + 10) |
| CatalogoPublico | 5 | 11 | 3 | 2 | 6 (6 + 0) |
| **TOTAL** | **45** | **119** | **33** | **14** | **68** |

- **CI ejecuta solo `@listo` con `--strict`** → esas 14 features (68 casos) deben estar 100 % verdes para integrar. Es la cifra honesta de "verificado en pipeline": **68/68 en verde es condición de merge** (no marcadas de otro modo).
- Las 31 features restantes están fuera de CI (en desarrollo: pasos pending/undefined permitidos).

### 3.3 Tests Pest/PHPUnit
| | Unit | Integration | Feature | Total |
|---|---|---|---|---|
| InventarioGestionColeccion | 40 | 8 | 0 | 48 |
| CatalogoPublico | 3 | 1 | 0 | 4 |
| GestionPrestamosRecepciones | 2 | 0 | 0 | 2 |
| Raíz `app/` | 1 | 0 | 10 | 11 |
| **TOTAL** | **46** | **9** | **10** | **65** |

### 3.4 Tiempos de búsqueda / rendimiento
- **No hay benchmarks numéricos** en el repo.
- Sí hay **37 índices** definidos en migraciones orientados a búsqueda (`occurrence_id`, `nombre_canonico`, `['rango','nombre_canonico']`, `codigo_muestra`, `estado_revision`, `['especimen_id','es_actual']`, etc.).
- Único control temporal: feature `@listo` `monitoreo_tiempo_extraccion.feature` (valida tiempos de extracción de especímenes, no es benchmark de latencia de búsqueda).

### 3.5 Pipeline CI
`.github/workflows/tests.yml`: PHP 8.4 y 8.5; instala deps; comando crítico:
```bash
vendor/bin/behat --profile=default --no-interaction --tags=@listo --strict
```

---

## 4. Cronograma de 4 iteraciones (reconstruido desde git: 435 commits, 25-mar → 24-jun-2026)

| Iter | Fechas | Hito / evidencia (PRs y migraciones) | Entregable |
|---|---|---|---|
| **1 — Fundación** | 2026-03-25 → 2026-05-06 | "Set up a fresh Laravel app"; scaffolding nwidart + Clean Architecture; migraciones IoT base `2026_05_01_*` (gabinetes, ranuras, cajas, ubicaciones) | Arquitectura modular, Bounded Contexts y subsistema IoT base |
| **2 — Inventario físico + sincronización** | 2026-05-07 → 2026-05-30 | PR #6/#8–#14; migraciones taxones/especímenes `2026_05_12/13_*`, identificadores, unit trays `2026_05_24/28_*` | Registro de ubicación de cajas (IoT), núcleo taxones/especímenes, sincronización de especímenes (CatalogoPublico) |
| **3 — Calidad de datos, ETL y orden taxonómico** | 2026-06-01 → 2026-06-07 | PR #15 (incongruencia taxonómica), **#16 (migración catálogo invertebrados = ETL)**, #17–#26; extensión Darwin Core `2026_06_01_000008`, endurecimiento import `…000011` | Importador ETL Darwin Core, alertas de orden taxonómico, centro de revisión, filtros responsive |
| **4 — Trazabilidad operativa + curado BDD** | 2026-06-08 → 2026-06-24 | PR #27 (workflow BDD/`@listo`), #28 (mapa/guía de especímenes), #30 (gestión información taxonómica + sincronización); migración `visitantes` `2026_06_15` | Mapa de ubicación/guía, QR de visitantes, gobernanza del pipeline `@listo`, sincronización taxonómica final |

> Las fechas son límites inferidos por la fecha de los merges/migraciones; ajústalas a las fechas oficiales de planificación de tu equipo si difieren.
