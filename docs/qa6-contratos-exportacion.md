# Perfil de exportación pública 2.0 y contratos QA6-004/005

Perfil: `hubdigital.portal-publico/2.0`, definido en `PerfilExportacionPublica`.
La versión identifica el esquema y su significado; la versión de despliegue identifica
el código que produjo los datos. Este perfil es un intercambio del portal con varios
términos Darwin Core y extensiones locales, no una declaración de conformidad de un
Darwin Core Archive completo.

## Tipo nomenclatural y disposición

`typeStatus` procede exclusivamente de `taxonomia.especimenes.type_status`; `typeNotes`
procede de `type_notes`. `disposition` procede de `disposition`. El importador vigente
ya mantiene esos campos separados, por lo que esta corrección no migra ni adjudica
tipos desde el campo de disposición.

Un tipo ausente permanece `null` en los datos públicos y vacío en las descargas;
en la lectura humana se representa como no informado o un guion. No significa
«no es tipo». Los valores desconocidos no vacíos se conservan literalmente.
`in_collection` y `in collection` son variantes de disposición y pueden mostrarse
como «En la colección»; no se usan para completar `typeStatus`.

El filtro público `fsti` consulta sólo el estado nomenclatural y admite sus etiquetas
traducidas conocidas, como «Holotipo». El filtro independiente `fd` consulta la
disposición: `in_collection`, `in collection` y «En la colección» seleccionan la misma
población. Los enlaces del portal y la reconstrucción de selección del chat preservan
`fd`. Ningún alias transforma un valor curatorial desconocido en un tipo conocido.

Por compatibilidad de privacidad, tanto tipo como disposición siguen sujetos al
permiso `type_status_visible`: antes ese mismo permiso controlaba el campo
`disposition` presentado erróneamente como tipo. La separación semántica no amplía
su visibilidad. El mismo permiso se exige para filtrar por cualquiera de los dos.

TDWG sitúa [typeStatus](https://dwc.tdwg.org/terms/#dwc:typeStatus) en identificación
nomenclatural; [disposition](https://dwc.tdwg.org/terms/#dwc:disposition) describe dónde
se encuentra el material y ofrece ejemplos de presencia en colección y préstamo.
Referencia primaria consultada el 2026-10-03. Las convenciones de `occurrenceStatus`
permanecen explícitas en este perfil: el adaptador conserva el valor registrado y,
para la ausencia histórica de ese campo en el inventario publicado, usa `present`.
Ese comportamiento previo no determina ni completa `typeStatus` o `disposition`.

## Coordenadas y procedencia

`georeferenceRemarks` conserva exactamente la nota pública de `lat_lon_max_error`,
disponible como `coordinateReference` en el proveedor. Así, un XLSX conserva la
advertencia de coordenadas recuperadas del Excel y precisión pendiente, o una
referencia aproximada, junto al par publicado.

`coordinateUncertaintyInMeters` permanece vacío: la fuente de este perfil no aporta
una distancia métrica curada. No se extrae un número de una nota ni se estima un radio
a partir de la cantidad de decimales. TDWG permite dejar vacío el término cuando la
incertidumbre se desconoce y excluye cero. La advertencia se interpreta conforme a
[georeferenceRemarks](https://dwc.tdwg.org/terms/#dwc:georeferenceRemarks), y la distancia
conforme a [coordinateUncertaintyInMeters](https://dwc.tdwg.org/terms/#dwc:coordinateUncertaintyInMeters).

Las coordenadas XLSX se exportan como un par: ambas deben ser visibles, estar presentes
y quedar dentro de los límites de latitud/longitud. La nota requiere los dos permisos
de coordenadas. La nota puede conservarse aunque falte el par; describe la procedencia
y no afirma una precisión validada. Los campos de localidad original y referencia INEC
requieren `locality_name_visible`. No se fusionan como si la referencia administrativa
fuera la transcripción de la etiqueta.

## Diferencias entre formatos

| Formato | Población y contenido | Identificación del perfil |
| --- | --- | --- |
| CSV de resultados | Toda la selección pública aplicada, con 14 columnas de identificación y contexto geográfico. Respeta filtros y permisos, aunque se descargue desde una página intermedia. | Columna `Perfil de exportación` y cabecera HTTP `X-HubDigital-Export-Profile`. |
| XLSX de especie | La selección aplicada dentro de la especie, con 26 columnas científicas y de procedencia. Números y fechas aptos se escriben como valores nativos; textos permanecen literales. | Columna `exportProfile` y cabecera HTTP `X-HubDigital-Export-Profile`. |
| JSON/GeoJSON del mapa | Agrupaciones públicas de pares exactos y sus multiplicidades; no es una exportación completa de todos los campos de cada ejemplar. | Contrato cartográfico existente, fuera del perfil tabular 2.0. |
| Tabla/ficha | Datos públicos y etiquetas de lectura, con los mismos campos fuente para tipo, disposición y nota de coordenadas. La tabla presenta campos adicionales que no pertenecen al resumen CSV. | Interfaz humana; los valores originales de las descargas no se sustituyen por traducciones. |

El número de catálogo puede repetirse. Las filas se seleccionan mediante el UUID
estable del ejemplar y se conserva su multiplicidad; una etiqueta repetida no autoriza
deduplicar ni sobrescribir registros. El UUID interno no se agrega a las descargas.

Orden CSV 2.0 (las primeras doce posiciones se conservan):

1. `N.º catálogo`
2. `Taxón`
3. `Fecha`
4. `Localidad del Excel`
5. `Localidad INEC`
6. `Código INEC`
7. `Provincia`
8. `Latitud`
9. `Longitud`
10. `Precisión` (nota textual, no incertidumbre en metros)
11. `Tipo` (`typeStatus`)
12. `Referencia INEC`
13. `Disposición` (`disposition`)
14. `Perfil de exportación`

Orden XLSX 2.0 (las primeras diecinueve posiciones se conservan):

`occurrenceID`, `scientificName`, `typeStatus`, `occurrenceStatus`, `individualCount`,
`localityName`, `country`, `decimalLatitude`, `decimalLongitude`, `recordedBy`,
`samplingProtocol`, `typeNotes`, `specimenNotes`, `stateProvince`,
`minimumElevationInMeters`, `maximumElevationInMeters`, `eventDate`, `caste`, `lifeStage`,
`disposition`, `georeferenceRemarks`, `coordinateUncertaintyInMeters`, `localityExcel`,
`localityInec`, `localityInecReference`, `exportProfile`.

Las celdas vacías pueden corresponder a ausencia del dato o reserva por permisos;
el perfil no permite distinguir esas causas con inferencias adicionales.
`localityName`, `typeNotes`, `specimenNotes`, `caste` y las columnas `localityExcel`,
`localityInec`, `localityInecReference`, `exportProfile` son nombres del contrato local.
El consumidor debe leer el esquema versionado antes de interpretarlos como términos
Darwin Core. Los consumidores basados en posiciones o en 12/19 columnas deben
adaptarse al perfil 2.0.

## Evidencia, cobertura y pendientes

La revisión QA6 observó en MEPN-INV-28972 `typeStatus=in_collection` en XLSX y un tipo
vacío en CSV; el perfil 2.0 elimina ese mapeo cruzado. QA6 documentó notas omitidas en
los XLSX de MEPN-INV-45400 y MEPN-INV-45469. El cambio conserva el campo fuente de esas
notas y un control con referencia aproximada, sin añadir una distancia fabricada.

Las nuevas pruebas Pest tienen estos alcances, y su ejecución pertenece al siguiente
`crear-paquete-oci` completo:

- Adaptador: campo nomenclatural, notas de tipo y disposición separados; tipo nulo y
  texto curatorial desconocido; lectura por número de catálogo y UUID.
- Unidad de exportación: lectura del XLSX producido, coordenadas numéricas, nota textual
  preservada, incertidumbre vacía, procedencia de localidad y reserva de campos.
- Flujo con PostgreSQL: igualdad de población entre filtros independientes, tabla,
  CSV y XLSX; alias de etiquetas; selección del chat y protección del filtro reservado.

Los fixtures son sintéticos y comprueban el contrato. No acreditan una decisión
curatorial ni la ejecución actual de la selección de producción. La reprueba de los
registros reales citados, con el nuevo despliegue y ambas descargas, queda pendiente.
No se agregan escenarios Gherkin que repitan estas mismas entradas, flujo y resultado.

Se revisaron estáticamente fuentes, mapeos, permisos y el esquema del generador XLSX.
No se ejecutaron Pest, Behat, validación sintáctica, compilaciones ni el paquete.
