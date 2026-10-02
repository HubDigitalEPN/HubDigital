# Recursos taxonómicos fotorrealistas del portal

Fecha: 2026-10-02. Solicitud: sustituir el aspecto de dibujo de las representaciones por imágenes con aspecto de fotografía macro, conservando su carácter educativo.

## Identidad y uso público

Las veinte imágenes son **representaciones fotorrealistas generadas con image_gen**, no fotografías de ejemplares de la colección ni evidencia para identificar especies. Los nombres y el linaje de cada especie proceden de datos públicos de la base de datos y se muestran mediante la ficha visual `representacion-especie`: retrato grande de grupo, nombre propio y clasificación pública compacta y plegable. La imagen de grupo se comparte por sus ancestros publicados; no se atribuye una anatomía exclusiva a una especie. Si no se conoce un grupo representable, se conserva el linaje real abierto y se informa que no hay morfología disponible. Las fotografías públicas auténticas siguen teniendo prioridad en la galería y el mosaico.

Los enlaces del servicio añaden `?v=20261002-foto1` a los WebP para renovar la caché tras el reemplazo. Los archivos conservan sus rutas físicas. Las comprobaciones de existencia usan la parte PATH de la URL, y el límite contractual continúa siendo inferior a 20 000 bytes por imagen.

## Producción y referencias

Se utilizó la herramienta integrada `image_gen.imagegen`, sin modelos ni procesos de generación en la VM. Dos atlas originales contienen diez imágenes cada uno. Se exportaron sus celdas a WebP de 320 × 320 píxeles, calidad 78, con margen blanco y proporciones conservadas. Pillow se empleó únicamente para recorte de celdas, escala y codificación. Las correcciones de contenido se hicieron con **image_gen**, no dibujando o borrando píxeles mediante scripts.

AntWeb sirvió de orientación para la presentación de ejemplares en fotografía macro sobre blanco. Se consultaron [el registro de Neoponera apicalis CASENT0626832](https://www.antweb.org/specimen.do?code=casent0626832), [las imágenes de Dolichoderus](https://www.antweb.org/images.do?countryName=Indonesia&genus=dolichoderus&rank=genus&subfamily=dolichoderinae) y [la documentación oficial de imágenes del API](https://www.antweb.org/documentation/api/apiV3.jsp). No se copiaron ni incorporaron fotografías de AntWeb. Los recursos generados no se rotulan como Neoponera, Dolichoderus u otra especie particular.

Criterios enviados a los dos atlas: fotografía macro de laboratorio con detalle de cutícula y textura natural, foco apilado, fondo blanco, sombras suaves y margen amplio; sin rótulos, alfileres, marcas de agua, escalas ni estilo vectorial/caricatura. Disposición de cinco columnas por dos filas. Se solicitaron rasgos generales plausibles de los grupos, sin diagnóstico de especie: seis patas para insectos, ocho para arañas, cuatro pares de patas de marcha y pedipalpos para escorpiones, patas y pinzas de cangrejo, siete pares para isópodos, segmentación de anélidos, ausencia de segmentación externa de nematodos y pares de patas propios de diplópodos.

- Atlas A, por filas: Formicidae, Coleoptera, Lepidoptera, Diptera, Orthoptera; Odonata, Phasmatodea, Hemiptera, Araneae, Scorpiones.
- Atlas B, por filas: Decapoda, Isopoda, Gastropoda, Annelida, Nematoda; Diplopoda y cuatro variantes distintas de Formicidae (oscura lateral, rojiza lateral, ámbar dorsal y cabeza ancha oscura).

Los originales permanecen fuera del paquete en `C:\\Users\\Usuario\\.codex\\generated_images\\01a0fd47-71d9-7461-a357-d440fa030b43`:

| Recurso | Original PNG |
| --- | --- |
| Atlas A | exec-60a769e2-2cdd-44b2-bc47-12b1521ffdb5.png |
| Atlas B | exec-8061790e-942d-48ea-8261-17ac5096ff06.png |
| Formicidae 1, antena completa y margen | exec-d0162b25-63ea-4e4d-a327-2f82667ad397.png |
| Formicidae 3, sin fragmentos ajenos | exec-d2a0e885-494f-4716-864f-2d9cd0d91ee0.png |
| Formicidae 4, antena completa y margen | exec-fb2d4696-fec9-4255-89a6-7d07982cf718.png |
| Phasmatodea, sin fragmento ajeno | exec-73a8c454-c0eb-4275-95a8-a42e1e14f53a.png |
| Odonata, alas completas y margen | exec-d1fb0966-f2b3-4108-85f4-6dcc596b105a.png |

Las cinco ediciones conservaron el animal principal, pose, anatomía general y textura; eliminaron fragmentos de celdas vecinas o completaron el borde de una antena/ala recortada y añadieron margen blanco.

## Recursos desplegados

Todos se encuentran en `public/images/taxonomia/`. Peso total: 162154 bytes; no se despliegan los atlas PNG de alta resolución ni duplicados por especie.

| Archivo WebP | Bytes |
| --- | ---: |
| formicidae.webp | 6650 |
| coleoptera.webp | 8756 |
| lepidoptera.webp | 14796 |
| diptera.webp | 8252 |
| orthoptera.webp | 9900 |
| odonata.webp | 7898 |
| phasmatodea.webp | 4424 |
| hemiptera.webp | 7606 |
| araneae.webp | 8550 |
| scorpiones.webp | 9002 |
| decapoda.webp | 12668 |
| isopoda.webp | 8502 |
| gastropoda.webp | 9370 |
| annelida.webp | 7048 |
| nematoda.webp | 3612 |
| diplopoda.webp | 10000 |
| formicidae-1.webp | 6000 |
| formicidae-2.webp | 5818 |
| formicidae-3.webp | 6794 |
| formicidae-4.webp | 6508 |

## Revisión y cobertura

Se inspeccionaron visualmente los veinte WebP como archivos estáticos y los cinco resultados corregidos. No se ejecutó la aplicación, navegador ni suites independientes por el agente de UI para esta revisión. Las pruebas existentes y ampliadas de `IlustracionTaxonomicaTest` comprueban el grupo escogido, el carácter representativo y generado, rutas/versionado, disponibilidad y límite de peso; `RepresentacionEspeciePublicaTest` comprueba nombres distintos, procedencia, clasificación semántica accesible, todos los rangos recibidos (incluidos intermedios/repetidos/desconocidos), escape y ausencia de anatomía o familia inferida para casos desconocidos. Las aserciones dependen de esos contratos públicos, no de la posición de textos dentro de un SVG. Su ejecución corresponde exclusivamente al siguiente `crear-paquete-oci` completo.
