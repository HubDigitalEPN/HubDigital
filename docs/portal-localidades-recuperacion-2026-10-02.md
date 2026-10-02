# Recuperación de la localidad original

La revisión visual adicional encontró fichas con «Localidad original» vacía aunque `localityName` contenía el sitio de colecta. El lector Excel entrega celdas vacías como cadenas `''`; el mapper aplicaba `??` antes de limpiar esas cadenas y descartaba el dato alternativo. El mapper ahora toma la primera cadena no vacía, en el orden `localidad_verbatim`, `verbatimLocality`, `localityName`. Conserva la normalización y descomposición existentes y respeta valores explícitos de país y provincia.

La migración nueva `2026_10_02_000015_restore_original_locality_verbatim.php` recupera únicamente `localidad_verbatim` ausente (`NULL` o espacio/vacío). No reescribe la migración 000012 ya publicada. No modifica localidades corregidas, `locality_name`, provincia, protocolo, coordenadas, taxonomía, códigos ni permisos. Tampoco reemplaza un texto original ya existente.

## Procedencia y selección

Fuente versionada: primera hoja `Colección_principal` de `docs/Catálogo lab invertebrados EPN (25-sep-2026).xlsx`. SHA-256 documentado en la [bitácora de curación](coleccion-principal-curacion-2026-09-29.md): `8ad5eae2eb4935d8629c9b42384b7eb5806f9be109de469dd9ccb5db44bd7114`. La extracción no reimporta la colección ni conecta con la base de datos.

Derivado incluido en el paquete: `resources/data/coleccion-principal-localidades-20260925.csv.gz`, CSV UTF-8 con columnas `fila_origen_excel,occurrence_id,old_code,localidad_verbatim`. Contiene 35.251 textos completos de localidad entre 49.696 filas no vacías de la fuente; ocupa 285.947 bytes comprimidos. El texto más largo tiene 150 caracteres. Todos los textos tienen `occurrence_id`; 14.600 tienen `old_code` vacío conocido.

La extracción lee los valores XML de la primera hoja y resuelve `sharedStrings`, sin convertir identificadores a números flotantes. El índice de origen excluye la cabecera y filas totalmente vacías, como `ExcelFuenteCatalogo`. Quita espacios exteriores igual que el mapper, escoge el primer campo de localidad no vacío en la prioridad indicada y conserva el texto completo, incluyendo caja, acentos y comas internas. CSV entrecomillado permite conservar también saltos de línea interiores. El texto de la recuperación histórica se guarda tal como aparece en la fuente, sin la normalización de caja que usa el mapper para nuevas cargas.

Cada recuperación exige simultáneamente `fila_origen_excel` (índice único del importador), `occurrence_id` exacto presente y `old_code` exacto. Un código anterior vacío de la fuente exige `old_code IS NULL` en el registro, mediante `IS NOT DISTINCT FROM`; no se omite esa comparación. Dos ejemplares con el mismo código público siguen siendo identidades distintas. La migración deja intactos los registros sin fila de origen, con identidades discordantes o incompletas y los textos que excedan 500 caracteres; requieren curatoría, sin inferencias ni truncamiento. Los conteos del derivado describen candidatos de la fuente, no el número de filas efectivamente restauradas en OCI.

## Memoria y comprobación

La migración usa `compress.zlib://`, `fgetcsv` y bloques de 500 filas. Actualiza únicamente el campo original mediante una consulta por bloque; no carga el Excel ni todos los especímenes en la VM de 1 GB. La invalidación existente de cachés por sentencia observa los cambios. Repetir la recuperación conserva las filas ya rellenadas y los valores curatoriales.

El caso nuevo de `FilaCatalogoMapperLocalidadTest.php` cubre celdas vacías de Excel, fallback y prioridades no vacías. El caso nuevo de `PortalCartografiaRealTest.php` cubre NULL y vacío, código anterior ausente conocido, identidad discordante, texto curado preservado, conservación de coordenadas/localidades/método e idempotencia. Las posiciones fuente del fixture se liberan exclusivamente dentro de la transacción que revierte el caso; no se presupone una base vacía.

La revisión del código, contratos y fuente fue estática. Los casos y la migración se ejecutan exclusivamente en la siguiente ejecución completa de `crear-paquete-oci`. La muestra visual de 22 UUID usa una base local independiente y su reparación acotada no sustituye esa validación ni una migración aplicada en OCI.
