# Asistente del portal de depósitos

## Implementación

- Laravel 13, Livewire 4 y PostgreSQL. El panel se abre con Alpine en el navegador, sin petición de red; el envío de mensajes utiliza Livewire.
- `ConocimientoPortal` combina aliases, términos normalizados, Full Text Search en español, `pg_trgm` cuando está instalado, probabilidad previa con suavizado de Laplace, transición desde el nodo anterior y calidad de feedback con estimación Beta. Los pesos y umbrales iniciales están en `config/chatbot.php`; el corpus de prueba permite calibrarlos.
- La migración del módulo crea `divulgacion.chat_nodes`, `chat_aliases`, `chat_variants`, `chat_synonyms`, `chat_transitions` y `chat_unmatched`. `pg_trgm` y `unaccent` se instalan si el servidor lo permite; la clasificación conserva un fallback sin ellas. La migración nativa evita dos fuentes de esquema. Los nodos publicados se sembraron con información visible en el portal y el flujo existente; un tema sin respuesta confirmada queda en borrador con `needs_curator_review`.
- El contexto del chat conserva solamente el último nodo, la última entidad de catálogo y tres variantes recientes. Se muestran como máximo 20 mensajes en el componente. No se guardan transcripciones personales en PostgreSQL.
- El feedback se acepta una vez por respuesta mediante un token guardado en la sesión del servidor. Las preguntas no reconocidas se agrupan por hash y se guardan con correo y números omitidos.

## Consultas y privacidad

- El asistente responde saludos, depósito temporal, donación, documentos, procedencia, revisión, entrega, acceso, contacto y ayuda para usar el catálogo. Las variantes cambian el estilo sin cambiar los requisitos.
- Para un nombre de taxón puede informar si hay registros públicos, el conteo exacto, un máximo de diez registros o un máximo de ocho especies. La consulta usa parámetros y solo lee especímenes con fila en `divulgacion.especimenes_divulgables` y banderas visibles de código y nombre científico. Un resultado cero se refiere a registros **publicados**, no al inventario privado.
- Los códigos de catálogo y otras preguntas taxonómicas siguen utilizando el manejador existente y su capa de visibilidad. No se permite ejecutar SQL generado a partir del texto libre.
- No hay un LLM ni embeddings residentes en OCI. El portal puede funcionar con 1 GB de RAM sin un servicio adicional. La autoverificación de la release informa el pico de memoria del proceso PHP que comprueba el chat; la memoria total de la VM se debe observar durante el despliegue real.

## Curaduría y despliegue

- Curaduría y administración acceden a **Divulgación → Asistente del portal**. Allí crean, editan, publican, desactivan, expanden, contraen, jerarquizan y reordenan nodos; editan preguntas equivalentes, variantes con peso y estado activo/inactivo, y sinónimos; prueban una pregunta; y asocian consultas pendientes a respuestas confirmadas. Cada variante se escribe como `peso|activo|texto` o `peso|inactivo|texto`.
- `crear-paquete-oci` incluye automáticamente `Modules/`, `config/` y `deploy/`. Su lista de archivos obligatorios ahora comprueba los componentes del chat; la migración se aplica con `artisan migrate --force` en el procedimiento existente. La activación ejecuta `deploy/oracle/scripts/verify-portal-chat.php` como `www-data` con el entorno protegido. **No se ejecutó `crear-paquete-oci` en este cambio**.
- En desarrollo Windows se aplican las migraciones `2026_09_26_000001` a `000005` del módulo con `php artisan migrate --path=... --force`. El corpus y las comprobaciones de privacidad, permisos y feedback están en `tests/Feature/PortalChatConocimientoTest.php` y `PortalChatSegundaIteracionTest.php`.

## Configuración

- `CHAT_ENABLED=true` activa el widget.
- `CHAT_SPECIMEN_SEARCH=true` activa la consulta controlada al catálogo.
- `CHAT_STATISTICAL_RANKING=true` activa contexto, probabilidad previa y calidad histórica.
- `CHAT_DEBUG=false` mantiene los logs de producción breves; `true` añade alternativas de ranking. Los logs nunca incluyen el texto de la pregunta.

## Segunda iteración: comprensión y decisión

El navegador abre el panel con Alpine y envía texto mediante Livewire. La decisión se realiza en PHP: `AsistentePortal` consulta `ConversacionBasica`, `DetectorEntidadesChat`, `ConsultaCatalogoPublico` y `ConocimientoPortal`. `TextoChat` normaliza acentos, puntuación y flexiones de intención sin alterar los nombres científicos. `ContextoChat` conserva durante 30 minutos el último nodo, entidades públicas pertinentes y tres variantes; al preguntar «¿Cuántos?» hereda un taxón o región pública, y «¿Y de Quito?» sustituye la geografía anterior. El servidor nunca acepta nombres de tablas ni SQL del visitante.

`RankingIntencionesChat` compara nodos publicados mediante coincidencia exacta, alias, tokens con sinónimos, `ts_rank_cd(to_tsvector('spanish', ...), plainto_tsquery('spanish', ...))`, `pg_trgm` cuando está disponible, contexto, transiciones y prior de uso. Los pesos iniciales están en `config/chatbot.php` y los vigentes en `divulgacion.chat_settings`, editables por curaduría. Cada componente queda visible en **Probar asistente**, que no incrementa usos ni escribe preguntas pendientes. El score es la suma ponderada; la confianza se calcula aparte con score, evidencia y margen frente al segundo candidato. Dos candidatos próximos generan una aclaración con opciones; una coincidencia insuficiente queda `UNKNOWN`. Las señales históricas tienen peso bajo para que no sustituyan al texto.

Las transiciones usan la frecuencia `from_id → to_id` con suavizado de Laplace: `(usos + 1) / (total_salida + número_de_nodos)`. El prior de intención es `(usos + 1) / (total_usos + número_de_nodos)`. La calidad utiliza la media Beta(útil + 1, no útil + 1). Solo cambia la selección de respuesta y un componente secundario del ranking, nunca los datos científicos ni el contenido institucional. Las variantes usadas recientemente se excluyen mientras queden alternativas.

Ejemplos comprobados: «quiero depositar» y «quiero donar» distinguen trámites; «¿qué requisitos hay?» sin contexto pide aclaración, mientras que después de depósito o donación elige el hijo correspondiente; «q papeles necesito para dejar unas muestras» se clasifica como documentos. El corpus curatorial de 29 frases incluye paráfrasis no sembradas como alias y permite ver confusiones. Las expresiones sociales «gracias», «ok», «quién eres» y despedidas se resuelven antes del catálogo.

## Catálogo, estadísticas y límites

La capa de entidades confirma taxones contra `taxonomia.taxones` y provincias/localidades contra campos divulgables reales. Las consultas de conteo, taxón, familias, géneros y geografía usan SQL parametrizado o un constructor fijo, con límites de salida. Las familias y géneros se muestran solo cuando existe un descendiente con ejemplares publicados y los campos requeridos visibles. No se revela la existencia de especímenes privados. Una búsqueda sin coincidencias dice «registros publicados», sin concluir que no exista material en el inventario interno.

`divulgacion.chat_metrics_daily` acumula conversaciones, mensajes, resueltos, `UNKNOWN`, consultas públicas, feedback, suma de tiempo y confianza. La confianza media divide solo entre respuestas clasificadas que recibieron un score; las frases sociales y los manejadores heredados no aportan una confianza artificial de cero. No se guardan transcripciones completas. `chat_unmatched` retiene una muestra corta con correos y números omitidos, agrupa variantes semejantes y permite asociar, crear, ignorar o resolver. `php artisan portal-chat:prune-unmatched --days=180` simula la retención de muestras **resueltas o ignoradas**; solo `--apply` las elimina. Las pendientes y las métricas agregadas permanecen. No se ejecutó ningún borrado en esta iteración.

Las migraciones `000001` a `000005` crean esquema, claves e índices y amplían el árbol con temas respaldados por el flujo real. La información no confirmada permanece en borrador y pendiente de curaduría. Las migraciones que contienen contenido curatorial no ofrecen un `down()` destructivo: revertir requiere decisión y respaldo explícitos. No se necesita Redis, Elasticsearch, LLM ni servicio residente. `CHAT_SEMANTIC_ENABLED=false` queda reservado para una etapa posterior; esta versión no carga modelos semánticos.

## Comprobaciones locales y preparación OCI

Durante el desarrollo se aplicaron solo las cinco migraciones del módulo en PostgreSQL local y se ejecutaron las pruebas `PortalChatConocimientoTest.php` y `PortalChatSegundaIteracionTest.php`. El corpus se evalúa como parte de las pruebas. Lotes locales de 100 llamadas promediaron: normalización 0,01 ms, extracción de entidades 3,22 ms, ranking 12,5 ms y consulta de familias divulgables 2,8 ms. Son medidas por componente, no una latencia de extremo a extremo ni una predicción para OCI. `EXPLAIN` para la consulta FTS mostró uso de `divulgacion_chat_nodes_status_position_index`; con el límite de 250 nodos no se justifica un nuevo índice GIN en esa consulta. La interfaz se revisó en Chrome headless a 320, 360, 375, 390, 430, 768, 1024, 1280, 1440 y 1813 px.

El empaquetador exige los archivos nuevos y el staging verifica su sintaxis. Tras migrar, `verify-portal-chat.php` comprueba tablas, columna de confianza, saludo, donación, ambigüedad y consulta pública; falla la activación si algo no coincide. **No se ejecutó `crear-paquete-oci` ni se desplegó esta segunda iteración.**

## TERCERA ITERACIÓN — CALIBRACIÓN Y ROBUSTEZ

### Corpus y método

El corpus contiene **156 preguntas**: TRAIN 94, CALIBRATION 31 y TEST 31 (aproximadamente 60/20/20). Hay **17 preguntas fuera de dominio** en total, cuatro en TEST. Incluye saludos, conversación, depósito, donación, documentación, revisión, entrega, acceso, contacto, catálogo, ambigüedad, correcciones, negaciones, errores ortográficos y referencias breves. Los casos con requisitos contextualizados identifican el nodo hijo esperado. Se corrigieron cuatro etiquetas de TRAIN para reflejar el subtema institucional que realmente preguntan.

El vocabulario de la migración `000008` se derivó de TRAIN. La búsqueda de 486 combinaciones de pesos y umbrales usa únicamente CALIBRATION, reutilizando señales ya calculadas para no repetir SQL. TEST se leyó al final; su primera lectura detectó un defecto en el evaluador de saludos y en la detección genérica de conteos sin «tienen». Después de corregir esos defectos generales, se repitió TEST una vez para obtener las cifras válidas siguientes. TEST no se utilizó para seleccionar pesos. La herramienta muestra la sugerencia al curador, pero **no la publica automáticamente**.

| Configuración en TEST | Top‑1 | Top‑2 | Precisión macro | Recall macro | F1 macro | F1 ponderado | Fuera de dominio |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Vigente | 51,61 % (16/31) | 67,74 % (21/31) | 0,4333 | 0,4556 | 0,3978 | 0,4462 | 4/4 correctas |
| Sugerida, sin publicar | 61,29 % (19/31) | 77,42 % (24/31) | 0,5796 | 0,5667 | 0,5277 | 0,5826 | 4/4 correctas |

En CALIBRATION, Top‑1 fue 58,06 % con los ajustes vigentes y 90,32 % con la sugerencia. La diferencia entre CALIBRATION y TEST evidencia que esa partición pequeña permite sobreajuste; no se debe anunciar 90,32 % como desempeño general. Las métricas por intención, matriz completa y 20 errores de mayor confianza se muestran en curaduría. Las intenciones con un solo ejemplo tienen precisión/recall/F1 inestables; ningún porcentaje por clase debe interpretarse como estimación poblacional.

Pesos vigentes: `exact=.20, alias=.18, keyword=.22, fts=.12, trigram=.10, context=.07, transition=.03, historical=.03, quality=.05`. Pesos sugeridos: `exact=.16575, alias=.14917, keyword=.30387, fts=.09945, trigram=.08287, context=.07735, transition=.03315, historical=.03315, quality=.05525`. Umbrales vigentes: `high=.70, medium=.43, unknown=.20, ambiguous_min=.35, min_margin=.08`. Sugeridos: `high=.70, medium=.22, unknown=.10, ambiguous_min=.35, min_margin=.08`. El margen entre los dos primeros candidatos forma parte de la confianza y tiene una prueba de regresión con 0,86 frente a 0,84 y frente a 0,35.

### Confianza y errores

Calibración observada en TEST con configuración vigente:

| Confianza | Casos con score | Aciertos | Exactitud |
| --- | ---: | ---: | ---: |
| 0,90–1,00 | 0 | 0 | sin muestra |
| 0,80–0,89 | 0 | 0 | sin muestra |
| 0,70–0,79 | 0 | 0 | sin muestra |
| 0,60–0,69 | 0 | 0 | sin muestra |
| 0,50–0,59 | 3 | 2 | 66,67 % |
| <0,50 | 20 | 6 | 30,00 % |

Los ocho casos resueltos por reglas sociales o catálogo no reciben score del ranking y se excluyen de la tabla. La confianza observada es monótona en las dos bandas con muestra, pero no hay datos para validar la interpretación de 0,70 o más. No se cambió la fórmula por un ajuste especulativo con tres ejemplos; el umbral sugerido sí cambia la decisión `UNKNOWN` en CALIBRATION. Una futura ampliación del corpus debe aportar casos de confianza alta y evaluar tasas de falso positivo.

Principales confusiones de TEST vigente: `documentos → UNKNOWN` (3), `deposito → UNKNOWN` (2), `revision → UNKNOWN` (2), `deposito → ambiguous` (1), `donacion → ambiguous` (1), `donacion → UNKNOWN` (1), `entrega → UNKNOWN` (1), `acceso → UNKNOWN` (1), `acceso_registro → UNKNOWN` (1) y `contacto → UNKNOWN` (1). `UNKNOWN` correcto para una pregunta fuera de dominio se contabiliza como acierto: los cuatro ejemplos de TEST fueron rechazados correctamente.

### Catálogo, contexto y seguridad

El catálogo combina taxón con provincia, localidad o país **visibles**, busca códigos `occurrence_id` públicos y conserva la grafía científica de la base. Ofrece una sugerencia solo si un nombre publicado tiene trigramas cercanos y distancia de edición de hasta dos caracteres; nunca autocorrige. Un resultado cero dice «no encontré registros publicados», sin inferir inexistencia biológica. La salida inicial limita nombres a diez. El detector agrupa candidatos de taxón en una consulta en vez de consultar cada palabra por separado; el evaluador también eliminó la consulta por ID de cada alternativa.

El contexto dura 30 minutos, conserva entidades públicas y hasta tres variantes recientes. «¿Tienen Dynastes?» seguido de «¿Cuántos?» conserva Dynastes; una corrección a Megasoma reemplaza el taxón; una respuesta institucional sobre donación limpia las entidades científicas previas. Dos sesiones simuladas mantuvieron Dynastes y Megasoma separados. **Nueva conversación** limpia contexto, mensajes y tokens de feedback de esa sesión. El historial del widget está limitado a 20 mensajes y no se guarda sin límite en `localStorage`.

El servidor rechaza 1 KB, 10 KB y 100 KB de texto antes de consultar; acepta como máximo 500 caracteres y 2.048 bytes. El control de frecuencia permite 30 mensajes/minuto por sesión anónima o 60 por cuenta autenticada, con un tope adicional de 300/minuto por IP. No necesita Redis. Las muestras desconocidas omiten correo, números extensos y cualquier texto que mencione credenciales. La importación JSON acepta hasta 2 MB, valida estructura, duplicados y ciclos, ofrece vista previa y usa transacción. Solo curaduría puede invocarla. La exportación contiene conocimiento, sinónimos y ranking; excluye usuarios, conversaciones, analítica y tokens. `chat_trace` guarda UUID de mensaje, hash HMAC de sesión, intención, nodo, variante, acción, confianza y duración; solo guarda filtros de catálogo cuando hubo resultados públicos. No almacena la transcripción. Una limpieza oportunista elimina trazas de más de siete días mientras hay tráfico; si el chat no recibe visitas, la limpieza queda pendiente hasta la siguiente consulta.

### Rendimiento local y despliegue

Presupuesto de desarrollo, como objetivo interno y no garantía OCI: conversación simple <30 ms; matching <100 ms; catálogo normal <100 ms; respuesta de lectura <250 ms. En el banco de lectura local final de **500 mensajes secuenciales** mezclados (conversación, catálogo, conocimiento y desconocidas), p50 **15,23 ms**, p95 **19,83 ms**, p99 **22,24 ms**, máximo **51,86 ms**, **0 errores**, pico del proceso PHP **6 MiB**. El banco llama a las mismas capas de decisión sin escribir analítica; no mide red, Livewire ni renderizado. Con 5 procesos simultáneos, 100 consultas dieron 0 errores, 4,49 s de pared y el peor p95 por proceso fue 25,34 ms. Con 10 procesos, 200 consultas dieron 0 errores, 7,24 s de pared y el peor p95 por proceso fue 28,41 ms. Estas pruebas se hicieron en Windows/PostgreSQL local; la VM OCI de 1 GB sigue sin medición.

`EXPLAIN ANALYZE` local mostró `Seq Scan` en `chat_nodes` (17 filas, 0,163 ms) y en `taxones` (una fila, 0,077 ms), apropiados para tablas pequeñas. La búsqueda de código usó `Seq Scan` sobre la tabla de especímenes vacía en esta base y un `Index Scan` preparado sobre `especimenes_divulgables.especimen_id` (ejecución 0,078 ms). Esos planes **no** prueban el costo con el catálogo completo del cliente; antes de escalarlo conviene repetir `EXPLAIN ANALYZE` con datos reales y considerar índices de expresión sobre `lower(occurrence_id)` y `lower(nombre_cientifico)` si los planes lo justifican.

Las migraciones `000006` a `000009` añaden versiones de ranking, contadores diarios de calidad, borradores y revisiones de nodos, vocabulario TRAIN y trazas mínimas. El editor guarda borradores sin alterar la respuesta publicada; permite probar «publicado» y «borrador», y publicar de forma explícita. La publicación registra actor, versión y fecha. El ranking guarda versiones con comentario y permite restaurar. La comparación A/B es offline, sin repartir usuarios públicos. El empaquetador exige los archivos nuevos; staging comprueba su sintaxis y la verificación de release comprueba esquema, corpus y respuestas esenciales. La verificación local del asistente pasó con pico PHP de 6 MiB. **No se ejecutó `crear-paquete-oci` ni se desplegó en OCI.**

Limitaciones concretas: expresiones como «no es depósito, es donación» todavía pueden pedir aclaración; preguntas breves sobre documentos, revisión o acceso pueden quedar `UNKNOWN`; con pesos sugeridos algunas preguntas de entrega se confunden con inicio de depósito; la geografía de un taxón solo existe si el dato está marcado como público; la sugerencia de nombres usa distancia simple y puede omitir errores más grandes; los conteos de familias/géneros en un catálogo grande requieren una nueva medición. La herramienta de importación deja nodos en borrador, pero aplica sinónimos y ranking al confirmar la importación, tal como avisa la vista previa. No hay medición de extremo a extremo ni en la VM de 1 GB.

## CUARTA ITERACIÓN — CALIDAD DE CLASIFICACIÓN

### Diagnóstico y taxonomía

El árbol tiene 17 nodos publicados. La tercera iteración obtenía 51,61 % Top‑1 y 67,74 % Top‑2 en el TEST legado. El ranking plano generaba candidatos razonables, pero rechazaba muchos como `UNKNOWN`: títulos y aliases breves daban scores débiles, temas generales competían con temas específicos y la confianza penalizaba márgenes pequeños. El corpus anterior también era pequeño y algunos nodos representaban la misma tarea. **Estos resultados legados no se usaron para elegir nuevas reglas en esta iteración.**

`revision` y `revision_estado` comparten ahora la intención evaluada `revision`; `catalogo` y `catalogo_busqueda` comparten `catalogo`. Sus nodos y respuestas permanecen editables por separado. `documentos` y `deposito_requisitos` siguen separados: los primeros explican archivos y permisos, mientras los segundos explican condiciones generales. No se borró ni dividió ningún nodo institucional. El clasificador informa dominio, intención, tema y acción sin asumir que un nodo equivale siempre a una intención distinta.

La selección queda **híbrida**: conversación y catálogo público tienen manejadores previos; para conocimiento institucional la primera etapa calcula coincidencia exacta, aliases, palabras, FTS, trigramas y señales históricas sobre los nodos publicados. La segunda aplica compatibilidad de dominio y tema, contexto, negación con alcance y penalización de nodos demasiado generales. Una pregunta mínima como «documentos» sin contexto ofrece opciones de nodos publicados; después de un depósito usa su contexto. El probador de curaduría muestra dominio, tema, nodo, dominio negado, candidatos, componentes, margen y confianza. Las aclaraciones ofrecen nodos reales. La respuesta desconocida distingue orientación dentro y fuera del dominio.

### Corpus y metodología

| Partición | Casos |
| --- | ---: |
| TRAIN | 170 |
| CALIBRATION | 57 |
| TEST legado | 31 |
| TEST_FINAL nuevo | 68 |
| CHALLENGE | 28 |
| **Total** | **354** |

Hay 35 preguntas etiquetadas fuera de dominio en total. TRAIN se amplió principalmente en clases débiles; `procedencia` sigue con solo dos casos y `documentos_firma` y `documentos_permisos` con tres cada una. El diagnóstico de similitud encontró una pareja casi duplicada en el TEST legado («muchísimas gracias»/«muchas gracias»); no encontró parejas con similitud textual de 85 % o más entre TEST_FINAL y desarrollo. TEST_FINAL y CHALLENGE no participaron en ajustes. El archivo [chat-fourth-evaluation.json](chat-fourth-evaluation.json) conserva **la única ejecución** de esos conjuntos. No se volvió a ejecutar tras leer el resultado.

La comparación A/B/C/D sobre CALIBRATION, antes de la última corrección de etiquetas de desarrollo, fue: plano Top‑1 0,3860/F1 macro 0,3317; estructurado 0,9298/0,8545; plano+Naive Bayes 0,5439/0,4471; estructurado+Naive Bayes 0,9123/0,8131. El experimento multinomial entrenó solo con TRAIN, con 14 clases, 343 rasgos y modelo serializado de 15.258 bytes. **Naive Bayes se descartó** porque empeoró el estructurado y añadió aclaraciones innecesarias. No se carga en producción. Tampoco se conservaron n‑gramas de caracteres como capa productiva; `pg_trgm` continúa activo.

El diagnóstico estratificado de cinco folds sobre TRAIN+CALIBRATION, con el motor fijo, dio Top‑1 medio **0,8399** (desviación **0,0553**) y F1 macro medio **0,8206** (desviación **0,0667**). Es una medida de variación entre grupos, **no** una validación independiente de entrenamiento: aliases y reglas del árbol son compartidos entre folds. CALIBRATION acabó con Top‑1 0,9649 y F1 macro 0,9162, pero esa cifra no predijo el holdout nuevo.

### TEST_FINAL único y CHALLENGE

| Métrica TEST_FINAL | Resultado |
| --- | ---: |
| Top‑1 | **36/68 = 52,94 %** |
| Top‑2 | 61,76 % |
| Top‑3 | 75,00 % |
| Top‑5 | 79,41 % |
| Precisión macro | 0,6201 |
| Recall macro | 0,5127 |
| F1 macro | **0,5215** |
| F1 ponderado | 0,5114 |
| Cobertura automática | 35/68 = 51,47 % |
| Precisión automática | **27/35 = 77,14 %** |
| Fuera de dominio | 7/7 rechazadas correctamente |

La diferencia frente a CALIBRATION muestra generalización insuficiente. En TEST_FINAL se registraron 22 `UNKNOWN` incorrectos, dos aclaraciones correctas y dos innecesarias. La clasificación diagnóstica provisional de los 32 errores fue: 11 generación de candidatos, 11 umbral/escasa evidencia, cuatro negación, tres reordenamiento, dos ambigüedad y uno contexto. Son categorías heurísticas para investigación, no causas demostradas. Top‑5 por debajo de 80 % indica que mejorar solo el reordenamiento no resolverá la mayoría de casos.

CHALLENGE fue 25/28 (89,29 %): breves 7/7, erratas 3/4, contexto 3/3, negación 3/3, corrección 0/2, ambigüedad 3/3, fuera de dominio 3/3 y catálogo 3/3. Los dos casos de corrección se ejecutaron como frases aisladas por el evaluador; requieren un taxón o filtro anterior. Una prueba de secuencia con contexto sí confirmó que «No Pichincha, Quito» sustituye el filtro, y la prueba anterior confirmó el reemplazo de Dynastes por Megasoma. El 0/2 del conjunto estático no debe presentarse como una medición del flujo conversacional completo.

### Cobertura, confianza y seguridad de respuesta

Curva descriptiva en CALIBRATION con el motor estructurado; no se eligió otro umbral tras observar TEST_FINAL:

| Umbral MEDIUM | Cobertura | Precisión automática | Aclaración/UNKNOWN |
| ---: | ---: | ---: | ---: |
| 0,30 | 84,21 % | 100 % | 15,79 % |
| 0,40 | 84,21 % | 100 % | 15,79 % |
| 0,50 | 82,46 % | 100 % | 17,54 % |
| 0,60 | 66,67 % | 100 % | 33,33 % |
| 0,65 | 56,14 % | 100 % | 43,86 % |

La precisión perfecta de esa curva pequeña no se extrapola: en TEST_FINAL fue 77,14 % con la configuración vigente. Antes de la política posterior al holdout, las bandas del ranking fueron HIGH 9/11 (81,82 %), MEDIUM 16/23 (69,57 %), LOW 2/7 y UNKNOWN 7/25 correctos. Al no demostrar una precisión HIGH de al menos 90 %, `chatbot.high_confidence_enabled=false` impide mostrar **HIGH** en producción; las respuestas se seleccionan igual y esa política no motivó una segunda lectura de TEST_FINAL. La cifra numérica de confianza sigue siendo un score del motor, no una probabilidad calibrada.

### Rendimiento, memoria y decisión

El banco local final de 500 consultas de lectura dio p50 **18,46 ms**, p95 **30,03 ms**, p99 **42,80 ms**, máximo 117,47 ms, cero errores y pico PHP **6 MiB**. Una corrida inmediatamente anterior dio p95 27,44 ms; existe variación local. La tercera iteración había medido p95 19,83 ms y pico 6 MiB en una mezcla de corpus anterior; las mezclas no son idénticas. La nueva capa no aumentó el pico observable en la resolución de 2 MiB del proceso PHP. No hay medición de VM OCI de 1 GB ni de Livewire extremo a extremo.

El empaquetador exige `SenalesIntencionChat.php`, staging valida su sintaxis y la verificación de release comprueba corpus ampliado y negación. La verificación local pasó. **No se ejecutó `crear-paquete-oci` ni se desplegó.** La decisión de calidad es **otra iteración antes de OCI**: TEST_FINAL Top‑1 52,94 %, F1 macro 0,5215, precisión automática 77,14 % y HIGH bruto 81,82 % no justifican pasar a la VM. La siguiente iteración debe ampliar vocabulario realmente nuevo y revisar generación de candidatos sin entrenar sobre TEST_FINAL.
