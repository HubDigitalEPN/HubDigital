# Plan y contraste del portal: cartografía y QA3

Solicitud del 2 de octubre de 2026. Base: `main`, rama `feat/portal-cartografia-qa3-20261002`.
El informe `qa_portal_parte3.md` es evidencia de la revisión de Dot del 1 de octubre; sus hipótesis se contrastan con el código. Las instrucciones de esta tarea prevalecen: la primera revisión de Codex es estática y las suites automatizadas se ejecutan únicamente en `crear-paquete-oci`. La ampliación solicitada por el usuario requiere, después de crear el paquete, una segunda revisión de los diez puntos y navegación real en Chrome o Edge visible, con corrección de los defectos encontrados y nuevo paquete si cambia el código. Dot conserva su revisión independiente del despliegue.

## Plan de acción y causas contrastadas

| Prioridad / evidencia | Causa o contrato observado en código | Cambio |
| --- | --- | --- |
| QA3-001 | Las tarjetas resolvían ejemplares por una etiqueta de catálogo no única. | Recuperar los UUID de la selección pública y paginar antes de materializar DTO. Conservar códigos y registros originales. Las fotos heredadas de códigos ambiguos se conservan para revisión y no se atribuyen a otro ejemplar. |
| QA3-002 | La exclusión de marcadores curatoriales no reconocía notas de reubicación. | Excluir esas notas de riqueza/taxonomía/geografía científica sin borrar la fila original ni inventar nombres. |
| QA3-003 y 005 | El detector extraía provincia y localidad homónimas independientemente; las comparaciones geográficas conservaban diferencias por acento. | Provincia/país no agregan localidad implícita. Normalizar las comparaciones sin modificar el dato fuente. |
| QA3-004 | El normalizador eliminaba guiones ISO y el detector solo reconocía meses/años. | Parser de límites diarios e ISO, intervalos y fechas válidas; pedir corrección cuando no pueda interpretar íntegramente el intervalo. |
| QA3-006 y 007 | Altitud, sexo, preservación y alternativas podían perder condiciones. | Aplicar elevación disponible; aclarar condiciones no soportadas y disyunciones antes de ofrecer un total parcial. |
| QA3-008 y 012 | Referencias estrechas y ayudas posteriores a reutilizar contexto científico. | Referencias ordinales a códigos, agregar/quitar mes, cambios de intención y respuestas concretas para acceso público, CSV y métricas. |
| QA3-009 | Ayuda de un único filo en un contenedor sin aislamiento del foco. | Ayuda contextual en cada tarjeta y diálogo nativo, cierre/Escape y devolución de foco. |
| QA3-010 | Metadatos en columnas y tamaños dependientes del viewport, no del ancho de ficha. | Adaptar al contenedor y permitir valores completos sin solapamiento. |
| QA3-011 | Propósito en botones sin estado de selección semántico. | Radios nativos excluyentes con `fieldset/legend` y restauración de selección. |
| QA3-013 y 014 | Toggle Flux en inglés y título de recuperación sin h1. | Control español con campo/estado identificable y encabezado principal semántico. |
| QA3-015 y 016 | Área invisible del contenedor del chat capturaba clics; cabecera distribuía todo en una fila estrecha. | Solo panel/trigger capturan puntero; acciones de cabecera en otra fila. |
| QA3-017 | Escape y fullscreen podían cerrar dos capas. | Priorizar diálogo superior y conservar maximización del mapa de fondo. |
| Capturas de mapa | Leaflet ya era cartográfico, pero los datos se redondeaban a 0,25° y una máscara oscurecía zonas sin registros. | Marcadores azules en latitud/longitud públicas originales; agrupar solo coordenadas coincidentes; conservar cartografía visible y exportar puntos WGS84 exactos. |
| Métodos de muestreo | La fuente Excel contiene `samplingProtocol`; el importador no preservaba ese campo. | Conservar protocolo original por espécimen y recuperar fuente usando identidad de fila, occurrence y código fuente, con procesamiento acotado. |
| Apertura, navegación y VM | Hidratar todos los ejemplares al construir tarjetas y repetir agregaciones aumenta trabajo/memoria. | Resúmenes SQL, consultas acotadas, 12 filas por página, apertura inmediata con estado de carga e índices selectivos. No particionar una colección pequeña sin evidencia de necesidad. |

## Representación científica

Taxón significa cualquier rango con un nombre científico. El gráfico solicitado es un árbol de clasificación taxonómica derivado de la base, no una genealogía evolutiva inferida. Cada especie muestra los datos publicados de sus propios ejemplares y el linaje disponible. El círculo indica cantidad de registros, no abundancia natural.

Los puntos mantienen la coordenada almacenada y su referencia de precisión. Situarlos exactamente no transforma una georreferencia territorial estimada en una localización GPS verificada. Se conservan las reglas de publicación y los campos reservados.

Las fotografías publicadas tienen prioridad en el mosaico. Las ilustraciones educativas son representativas de grupos y se identifican explícitamente: no documentan un ejemplar ni permiten identificar una especie. Todas las especies pueden resolver un recurso por su linaje; cuando no hay morfología compatible conocida se muestra un diagrama neutral. Esta entrega no inventa dibujos diagnósticos únicos para las aproximadamente 1.800 especies ni descripciones inexistentes en la base.

## Ilustraciones y procedencia

Se aplicó [imagegen](C:/Users/Usuario/.codex/skills/.system/imagegen/SKILL.md) con la herramienta integrada, sin llamadas API desde la aplicación. Recursos finales: `public/images/taxonomia/`. Dieciséis dibujos de grupos y cuatro variantes de hormigas, 320 × 320 px, WebP de aproximadamente 3–12 KB por archivo. Se solicitó un formato de poco peso como AVIF; el entorno no tiene codificador AVIF y se utilizó WebP. La VM sirve archivos estáticos compartidos, sin generar imágenes por solicitud ni cargar un modelo.

Prompts finales:

- Atlas científico en cuadrícula regular 4 × 4, un animal completo por celda sobre fondo blanco cálido, sin texto ni líneas, ilustración a tinta/lápiz de color de anatomía plausible: hormiga, coleóptero, mariposa, mosca, saltamontes, libélula, insecto palo, hemíptero, araña, escorpión, cangrejo, isópodo, caracol, anélido, nemátodo y diplópodo. Representantes genéricos, sin atribución a una especie científica determinada.
- Atlas Formicidae en cuadrícula 2 × 2, cuatro obreras genéricas completas: rojiza de perfil, pequeña oscura oblicua, esbelta ocre de perfil y robusta negra de perfil. Fondo blanco cálido, seis patas, antenas acodadas y cintura segmentada, estilo científico a tinta/lápiz de color. Sin etiquetas, fotografías simuladas ni atribución de especie.

Los originales generados se conservaron fuera del repositorio. Los archivos publicados son derivados acotados para la interfaz. La revisión taxonómica especializada de estas representaciones queda disponible para curaduría.

## Validación y publicación

Primera revisión propia: lectura de código, contratos, permisos, consultas, rutas, identidad, errores y efectos del diff, sin navegador. Después del primer paquete se realiza la revisión adicional con navegador visible solicitada por el usuario. No se ejecutan suites aisladas. Las pruebas nuevas se conservan en Pest y Node y se ejecutan únicamente dentro de `crear-paquete-oci`. Los escenarios Behat existentes siguen con `--profile=default --tags=@listo --strict`; no se duplican los casos nuevos de Pest en Gherkin.

El paquete debe terminar todas sus etapas antes de registrar y publicar el commit en `origin/main`. La identidad del commit y huellas se incorporan al artefacto. Crear el paquete no despliega una release; OCI aplica las migraciones antes de activarla. No se atribuyen mejoras de latencia medidas ni ausencia de regresiones visuales sin la evidencia de Dot.
