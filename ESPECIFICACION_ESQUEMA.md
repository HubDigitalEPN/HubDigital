# Especificación de esquema — Sistema de catálogo de invertebrados (MEPN)

> Documento de decisiones cerradas para implementar en Claude CLI.
> El sistema arranca **vacío**. La migración del Excel (~48.856 filas) ocurre **después**, no ahora.
> Objetivo doble: gestión interna de la colección + exportación a **GBIF/IPT (Darwin Core Archive)**.

---

## 0. Principios transversales (aplican a TODO el esquema)

1. **Cero pérdida en la migración.** Toda fila del Excel produce un registro. Ninguna fila se descarta jamás.
2. **Dato crudo preservado + enlace normalizado nullable.** Toda entidad que el Excel tiene sucia o sin estructurar (localidad, taxón, muestra, fecha, coordenadas, conteo) entra con:
   - un campo `*_verbatim` (texto) que guarda el valor original **tal cual**, y
   - una FK o campo estructurado **nullable** que se llena después, dentro del sistema.
3. **La normalización es una función del sistema, no un requisito previo.** Agrupar localidades parecidas, resolver taxones, confirmar muestras: todo ocurre adentro, con revisión humana, no durante el import.
4. **La calidad GBIF se impone en el EXPORTADOR, no en las restricciones de BD.** La BD es permisiva; el exportador a Darwin Core decide qué registro es publicable.
5. **Estado de revisión.** Las entidades con ambigüedad llevan `estado_revision` + `motivo_revision` para resolver dentro del sistema.

---

## 1. Convención de nombres

- Tablas y columnas de negocio: **español, snake_case** (`muestras_colecta`, `colector`).
- Columnas que mapean 1:1 a un término Darwin Core: conservar el término DwC en snake_case (`occurrence_id`, `basis_of_record`, `decimal_latitude`).
- El **mapeo a las etiquetas DwC oficiales** (camelCase: `occurrenceID`, `basisOfRecord`) se hace en el **exportador**, no en los nombres de columna.
- Importar **desde el bloque administrativo (columnas 1–30 del Excel)** por mejor calidad de dato. El bloque DwC (col 31+) es redundante y se ignora en el import.

## 2. Llaves primarias

- **UUID** como PK en todas las tablas (consistente con el esquema actual).
- `catalog_number` y `occurrence_id` son **atributos**, NO llaves primarias y **NO unique** (ver §4).

---

## 3. Modelo de datos — entidades y relaciones

### Jerarquía real detectada en los datos
```
muestra_colecta  (una trampa/salida: oldCode "BT2F3", fecha, sitio)
   └── contiene muchos →  especimen (sub-lote: N individuos de UNA especie)
                              └── individual_count = N
```
Una muestra agrupa varias morfoespecies; cada fila del Excel es un sub-lote de una especie
dentro de una muestra. **Preservar cada fila como registro** (decisión confirmada).

### Orden de implementación (respeta dependencias de FK)

**Sesión 1 — Tablas base (sin dependencias)**
- `taxones`
- `localidades`
- `entidades_depositantes`
- `dataset_config`

**Sesión 2 — Capa intermedia**
- `muestras_colecta`  (FK → localidades)
- `permisos`

**Sesión 3 — Núcleo**
- `especimenes`  (FK → taxones, localidades, muestras_colecta, entidades_depositantes)

**Sesión 4 — Dependientes**
- `identificaciones`  (FK → especimenes, taxones)
- `especimen_identificadores`  (FK → especimenes)
- `especimen_permiso`  (pivote especimenes ↔ permisos)

---

## 4. Tablas

### 4.1 `taxones` (Sesión 1)
Jerárquica vía autorreferencia. Soporta especímenes identificados a **cualquier rango**
(el 61% no llega a género; muchos scientificName son provisionales: "Acragas_sp.1").

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| id | uuid | PK | |
| nombre_cientifico | string(255) | no | puede ser provisional ("Azteca_sp.7") |
| rango | string(50) | no | kingdom/phylum/class/order/family/genus/species/morfoespecie |
| autor | string(255) | sí | |
| anio_descripcion | smallint | sí | |
| estado | string(50) | sí | aceptado / sinónimo / provisional |
| padre_id | uuid | sí | FK → taxones.id (autorreferencia) |

### 4.2 `localidades` (Sesión 1) — **SEPARADA del especimen**
Jerárquica. Resuelve los ~1.613 nombres casi-iguales del Excel. Se **puebla progresivamente**
por revisión; en el import los especímenes entran con `localidad_id = NULL` y su verbatim lleno.

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| id | uuid | PK | |
| nombre_canonico | string(255) | no | nombre oficial confirmado |
| rango | string(50) | no | pais/provincia/area_protegida/sector/sitio |
| padre_id | uuid | sí | FK → localidades.id |
| decimal_latitude | decimal(10,7) | sí | coordenada canónica |
| decimal_longitude | decimal(10,7) | sí | |
| geodetic_datum | string(60) | sí | |
| coordinate_uncertainty_m | decimal(9,2) | sí | requerido por calidad GBIF |
| **lat_lon_verbatim** | string(255) | sí | coords originales crudas |
| country | string(120) | sí | |
| state_province | string(120) | sí | |
| municipality | string(120) | sí | |

### 4.3 `entidades_depositantes` (Sesión 1) — ya existe
| Columna | Tipo | Null |
|---|---|---|
| id | uuid | PK |
| nombre | string(255) unique | no |
| tipo | string(50) | sí |
| contacto | string(255) | sí |

### 4.4 `dataset_config` (Sesión 1) — metadatos institucionales + EML para GBIF
Datos casi constantes que NO deben repetirse por fila (causa del peso del Excel).

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| id | uuid | PK | |
| institution_code | string(120) | no | p.ej. MEPN |
| collection_code | string(120) | no | INV |
| institution_id | string(255) | sí | |
| collection_id | string(255) | sí | |
| dataset_name | string(255) | sí | |
| owner_institution_code | string(120) | sí | |
| rights_holder | string(255) | sí | |
| access_rights | string(255) | sí | |
| license | string(120) | sí | |
| basis_of_record | string(60) | no | default "PreservedSpecimen" |
| information_withheld | text | sí | |
| eml_contacto | string(255) | sí | EML: contacto |
| eml_titulo | string(255) | sí | EML: título dataset |

### 4.5 `muestras_colecta` (Sesión 2)
La unidad de captura (trampa/salida). **HIPÓTESIS NO CONFIRMADA:** que oldCode tipo
"BT2F3"/"LT2F2" identifica la muestra. Por eso el vínculo es nullable y revisable.

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| id | uuid | PK | |
| codigo_muestra | string(120) | sí | oldCode tentativo (BT2F3) |
| fecha_colecta | date | sí | |
| fecha_verbatim | string(120) | sí | original crudo |
| localidad_id | uuid | sí | FK → localidades |
| localidad_verbatim | string(500) | sí | string original de localidad |
| colector | string(255) | sí | recordedBy |
| sampling_protocol | string(255) | sí | |
| estado_revision | string(40) | no | default "pendiente" |
| motivo_revision | text | sí | p.ej. "agrupación por oldCode sin confirmar" |

### 4.6 `permisos` (Sesión 2)
Separados porque un permiso cubre muchos especímenes (muchos-a-muchos).

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| id | uuid | PK | |
| tipo | string(40) | no | research / transport / export_import |
| numero | string(255) | sí | |
| responsable | string(255) | sí | "Responsible researcher for export" |
| detalles | text | sí | |

### 4.7 `especimenes` (Sesión 3) — NÚCLEO
Cada fila del Excel = un registro (sub-lote). PK interna UUID.

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| id | uuid | PK | |
| codigo_catalogo | string(100) | sí | interno |
| catalog_number | string(120) | sí | **NO unique** (se repite por muestra) |
| occurrence_id | string(120) | **sí** | **NO unique** — 3.963 IDs se repiten legítimamente; unicidad GBIF se genera en el exportador |
| old_code | string(120) | sí | |
| cardex_liquid_collection_code | string(120) | sí | |
| taxon_id | uuid | **sí** | FK → taxones; nullable (muchos sin identificar) |
| taxon_verbatim | string(255) | sí | scientificName crudo |
| muestra_id | uuid | **sí** | FK → muestras_colecta; nullable (hipótesis sin confirmar) |
| localidad_id | uuid | **sí** | FK → localidades; nullable |
| localidad_verbatim | string(500) | sí | string original ("Yasuní, Onkonegare") |
| entidad_depositante_id | uuid | sí | FK → entidades_depositantes |
| fecha_colecta | date | sí | parseada |
| **fecha_verbatim** | string(120) | sí | 2.755 fechas en español ("21-Jul-01") |
| fecha_colecta_fin | date | sí | datecollectedEnd |
| colector | string(255) | sí | recordedBy (fuente de verdad si no hay muestra) |
| individual_count | unsignedInt | sí | parseado |
| **individual_count_verbatim** | string(120) | sí | 89 valores no-numéricos ("RBSO9-2") |
| sex | string(40) | sí | |
| life_stage | string(40) | sí | |
| caste | string(60) | sí | solo himenópteros sociales |
| type_status | string(120) | sí | holotipo/paratipo — alto valor |
| preparations | string(120) | sí | |
| disposition | string(120) | sí | |
| occurrence_status | string(60) | sí | |
| decimal_latitude | decimal(10,7) | sí | parseada |
| decimal_longitude | decimal(10,7) | sí | |
| **coord_verbatim** | string(255) | sí | 1.226 con texto ("dañada") |
| elevation_min_m | decimal(9,2) | sí | |
| elevation_max_m | decimal(9,2) | sí | (el esquema viejo colapsaba ambos — separar) |
| habitat | string(255) | sí | |
| microhabitat | string(255) | sí | |
| biome | string(120) | sí | |
| biogeographic_region | string(120) | sí | |
| endemic | boolean | sí | |
| dna_notes | text | sí | |
| specimen_notes | text | sí | |
| occurrence_remarks | text | sí | |
| taxonomic_notes | text | sí | |
| acta_recepcion | string(120) | sí | #actaRecepcion |
| estado_revision | string(40) | no | default "pendiente" |
| motivo_revision | text | sí | p.ej. "catalogNumber duplicado, eventos distintos" |

### 4.8 `identificaciones` (Sesión 4) — historial, NO columnas planas
Un espécimen se re-identifica con el tiempo. Preserva la historia taxonómica.

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| id | uuid | PK | |
| especimen_id | uuid | no | FK → especimenes |
| taxon_id | uuid | sí | FK → taxones |
| identificado_por | string(255) | sí | identifiedBy |
| fecha_determinacion | date | sí | dateDetermined |
| calificador | string(60) | sí | identificationQualifier (cf., aff.) |
| observaciones | text | sí | identificationRemarks |
| es_actual | boolean | no | default true; solo una actual por espécimen |

### 4.9 `especimen_identificadores` (Sesión 4) — ya existe
otherCatalogNumbers y códigos alternos.

| Columna | Tipo | Null |
|---|---|---|
| id | uuid | PK |
| especimen_id | uuid | no (FK) |
| tipo | string(60) | sí |
| valor | string(255) | no |

### 4.10 `especimen_permiso` (Sesión 4) — pivote
| Columna | Tipo | Null |
|---|---|---|
| especimen_id | uuid | no (FK) |
| permiso_id | uuid | no (FK) |

---

## 5. Tareas de revisión que el sistema debe soportar (post-import)

Estas NO son limpieza del Excel; son funciones del sistema:

1. **Normalización de localidades** — agrupar `localidad_verbatim` parecidos (fuzzy matching, sin LLM), confirmar canónico, crear `localidades`, enlazar `localidad_id`.
2. **Confirmación de muestras** — validar si los grupos por `codigo_muestra` (oldCode) son muestras reales; enlazar `muestra_id`.
3. **Resolución de catalogNumber duplicados (Tipo A)** — pares con mismo catalog_number y fechas distintas (p.ej. MEPN:INV:1 con 1994 y 2006). Decisión humana.
4. **Resolución taxonómica** — enlazar `taxon_verbatim` provisional a `taxones`.
5. **Parseo de fechas en español** — `fecha_verbatim` "21-Jul-01" → date, con lógica de siglo (¿01 = 2001 o 1901?) y locale es.

---

## 6. Hallazgos del perfil de datos (referencia para el importador)

- **48.856 filas** en Colección_principal.
- **occurrence_id**: 99,9% lleno, pero **3.963 IDs repetidos afectando 8.801 filas** (lotes multi-especie + conflictos de catalogación). → no unique.
- **30 filas sin occurrence_id** → back-fill posterior, no bloquear.
- **Fechas**: 32.079 datetime + **2.755 texto en español** ("21-Jul-01", "11-Abr-01").
- **Coordenadas**: 63,2% llenas; **1.226 con texto** ("dañada") en lat y lon.
- **individual_count**: **89 valores no-numéricos** (códigos "RBSO9-2" mal ubicados).
- **Completitud taxonómica en cascada**: scientificName 5,9% vacío → family 41% → genus 61,7%.
- **verbatimLocality 99,1% vacío** — la localidad real vive en `localityName`.
- **Typos de campo a corregir en el mapeo**: invidualCount→individualCount, basisofRecord→basisOfRecord, dECimalLatitude→decimalLatitude, typECaste→typeCaste, iInfraspecificEpithet→infraspecificEpithet.

---

## 7. Lo que NO se decide aquí (pendiente del equipo)

- Confirmar si `oldCode` = muestra/trampa (hipótesis actual, marcada para revisión).
- Formato definitivo del `occurrence_id` generado para GBIF.
- Política de siglo para fechas de 2 dígitos.
