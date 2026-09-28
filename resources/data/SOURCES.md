# Localidades del Ecuador

El catálogo `ecuador-localidades-inec.json` contiene 59.880 localidades de la capa `loc_p` del [Marco Geoestadístico Nacional 2021 del INEC](https://www.ecuadorencifras.gob.ec/documentos/web-inec/Geografia_Estadistica/Documentos/GEODATABASE_NACIONAL_2021.zip), publicado en el [Geoportal del INEC](https://www.ecuadorencifras.gob.ec/documentos/web-inec/Geografia_Estadistica/Micrositio_geoportal/index.html).

Se conservan los códigos y nombres de localidad de esa edición. Los nombres de cantones y parroquias se relacionaron por sus códigos con el [Clasificador Geográfico Estadístico 2026](https://aplicaciones2.ecuadorencifras.gob.ec/SIN/descargas/cge2026.pdf). Esto no convierte la capa de localidades en una edición 2026. Ocho localidades mantienen el código histórico de parroquia `140452`, sin nombre equivalente en el clasificador 2026; no se les asignó otro territorio por aproximación.

La consulta, cantidades por provincia y las huellas SHA-256 de las fuentes están en los metadatos del JSON. La migración importa el catálogo una vez; el administrador puede crear, corregir o desactivar localidades sin borrar su vínculo con expedientes. Los cantones y parroquias son contexto para diferenciar localidades con igual nombre.
