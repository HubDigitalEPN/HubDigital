# Spike: Catalog of Life vs GBIF para validación taxonómica

**Fecha:** 2026-07-16
**Contexto:** Evaluar si conviene integrar la API de Catalog of Life (COL) para la
validación de nombres científicos —mencionada como autoridad más precisa para
macroinvertebrados— frente al GBIF que usamos hoy en
`GbifValidacionTaxonomicaAdapter` (puerto `ValidacionTaxonomicaPort`).

## TL;DR — Recomendación

**No reemplazar GBIF. Mantenerlo como validador primario.** Opcionalmente, más
adelante, añadir un adaptador de ChecklistBank como *segunda opinión* apuntando a
un dataset especialista de macroinvertebrados si se identifica uno y se necesita
esa precisión extra. La arquitectura (Port/Adapter) ya lo permite sin tocar el
resto del sistema.

## Hallazgo estratégico principal

**GBIF descontinuó la construcción de su propio backbone y lo está migrando al
"eXtended release" (x-release) de Catalog of Life.** Es decir, los datos contra
los que hoy hace match la API de GBIF *se están convirtiendo en Catalog of Life*.

- La API `species/match` de GBIF se mantiene (mismas keys, con servicio de
  redirección durante la transición; decisión tomada en la sesión técnica de
  nov-2024, migración en curso 2025–2026).
- El x-release = COL + fuentes taxonómicas/nomenclaturales adicionales (especies
  recién descritas, sinónimos, datos moleculares), con cobertura **más amplia**
  que el checklist COL "core".

**Implicación:** seguir en GBIF nos da la data de Catalog of Life (extendida) sin
migrar nada, y además conservamos el motor de matching de GBIF, que es superior
para nuestro caso de uso (ver abajo).

## Comparación técnica de las APIs de match

Pruebas en vivo contra la API real (julio 2026):

### GBIF — `GET https://api.gbif.org/v1/species/match`
- Devuelve `matchType` (EXACT / FUZZY / HIGHERRANK / NONE), **`confidence` 0–100**,
  y **`alternatives[]`** (con `verbose=true`).
- Es lo que alimenta hoy nuestra feature de sugerencias "¿quiso decir X?"
  (umbral de confianza ≥85, minado de alternativas — ver 2b del lote de mejoras).
- Sin autenticación, con cache y `Http::pool()` en batches.

### Catalog of Life / ChecklistBank — `GET https://api.checklistbank.org/dataset/3/match/nameusage`
(`dataset/3` = el checklist COL; se puede apuntar a *cualquier* dataset de
ChecklistBank, incluidos datasets especialistas.)

- Devuelve `type` (exact / variant / canonical / higherrank / ambiguous / none) y
  la clasificación completa del taxón aceptado.
- **NO devuelve un score numérico de confianza.**
- **NO devuelve una lista de candidatos fuzzy para errores tipográficos.**
  - Prueba con typo `Baetodes serratuss` → `type: higherrank` (resolvió solo al
    género *Baetodes*), sin sugerir *Baetodes serratus* ni `alternatives`.
  - Prueba con `Baetis` → `type: exact`, sin alternativas.
- Acceso de lectura público (GET de match no requiere key; solo se necesita
  cuenta GBIF para publicar/bulk). Soporta matching masivo asíncrono.

### Conclusión de la comparación
Para **nuestra UX actual** (validar + sugerir correcciones de tipeo al
depositante), GBIF es claramente mejor: COL/ChecklistBank no expone confianza ni
sugerencias fuzzy, así que migrar a COL *perdería* la feature de sugerencias que
acabamos de construir, y —al usar el COL core en vez del x-release— probablemente
**reduciría** cobertura.

## ¿De dónde viene "COL es más preciso para macroinvertebrados"?

Lo más plausible: no se refiere al **COL core** (dataset 3), sino a un **dataset
especialista** dentro de ChecklistBank (una Global Species Database del grupo de
interés). ChecklistBank permite hacer match contra cualquier dataset, no solo COL.
Para un grupo concreto de macroinvertebrados, una GSD especialista puede ser más
exhaustiva/actualizada que el backbone general. Ese es el único escenario donde
COL/ChecklistBank aportaría precisión superior a GBIF.

## Camino de integración (si se decide complementar en el futuro)

La arquitectura ya lo soporta limpiamente:

1. Crear `ChecklistBankValidacionTaxonomicaAdapter implements ValidacionTaxonomicaPort`
   en `Infrastructure/Adapters/`.
   - Endpoint: `GET /dataset/{key}/match/nameusage?q={nombre}&verbose=true`.
   - Mapear `type` → estado interno: `exact|variant|canonical` → `catalogado`;
     `higherrank|none` → `no_catalogado`; `ambiguous` → revisión.
   - Ojo: sin confianza ni fuzzy nativo → las sugerencias "¿quiso decir?" habría
     que construirlas aparte (`/nidx/pattern` regex o `/nameusage/search`), con
     más trabajo y peor calidad que el fuzzy de GBIF.
2. Elegir el `{key}`: `3` para COL core, o el key de una GSD especialista de
   macroinvertebrados (a identificar con el equipo curatorial).
3. Componer, no reemplazar: se puede envolver ambos adaptadores detrás del puerto
   (GBIF primario; COL para confirmar el nombre aceptado / segunda opinión) sin
   tocar el orquestador ni la UI.

## Decisión propuesta

- **Ahora:** no hacer nada de código. Quedarnos en GBIF; la migración de GBIF al
  x-release de COL nos da el beneficio de Catalog of Life "gratis".
- **Disparador para revisitar:** si el equipo curatorial identifica un dataset
  especialista concreto de macroinvertebrados en ChecklistBank cuya precisión
  supere a GBIF para sus taxones, entonces añadir el adaptador complementario.

## Fuentes

- GBIF Backbone → COL x-release: https://discourse.gbif.org/t/switching-gbif-s-taxonomic-backbone-to-the-catalogue-of-life-extended-release-x-release-gbif-technical-support-hour-for-nodes/5417
- GBIF Backbone Taxonomy (proceso, discontinuación): https://www.gbif.org/dataset/d7dddbf4-2cf0-4f39-9b2a-bb099caae36c
- Catalogue of Life API / ChecklistBank: https://www.catalogueoflife.org/tools/api · https://api.checklistbank.org/
- Name matching en COL (servicios y prospectiva): https://biss.pensoft.net/article/111662/
- Pruebas en vivo: `api.checklistbank.org/dataset/3/match/nameusage?q=...&verbose=true`
