# Contratos del chat revisados en QA4

El informe externo es evidencia de los recorridos observados, sin demostrar qué commit estaba desplegado. Este cambio atiende QA4-001, QA4-002 y QA4-004 y conserva los cuatro casos posteriores al paquete `0091625a` ya pendientes en `PortalChatContratoSeleccionTest.php`.

## Selección y unidad

`AsistentePortal` pasa la selección pública de la página a `ConsultaCatalogoPublico`. Una pregunta sobre «esta selección» o «selección actual» usa todos los criterios aplicados y la pareja `nivel`/`taxon`. Una selección de página vacía es autoritativa frente al contexto previo del chat. La ruta de resultado conserva esos criterios y cambia a Registros; la ayuda de mapa conserva los mismos criterios y cambia a Mapa. Página, vista previa, borrador, objetos de ejemplares y errores del cliente quedan fuera del contrato de selección.

`SeleccionPaginaChat` valida tipos, fechas, intervalos, coordenadas y valores acotados antes de construir `FiltrosBusqueda`. Las fechas inválidas y las selecciones malformadas producen una aclaración sin un conteo parcial. Sin página se puede contar la consulta pública anterior, identificándola en el texto; sin ninguna selección se pide aclaración. Los criterios explícitos adicionales junto a la referencia requieren confirmación en vez de fusionarse. Una consulta nueva o una petición global explícita conserva su alcance propio, aunque haya página filtrada o historial anterior.

Registros, especies, géneros y familias son unidades diferentes. Géneros/familias globales y por taxón cuentan cada nombre científico válido del rango una vez entre las identificaciones públicas de la población consultada. El total no se limita a los diez nombres presentados. Tanto las especies de una selección como las consultas de especies por criterios científicos usan el mismo linaje confirmado y presentan hasta ocho nombres. El ranking de familias con más registros permanece como una consulta distinta que rotula explícitamente sus cantidades de registros.

Los UUID confirman la selección y cada linaje antes del agregado por nombre. Dos identidades públicas con el mismo nombre científico y rango cuentan una vez, conforme a esa definición. Esta métrica no separa homónimos con autorías o linajes diferentes; resolverlos exige identidad curatorial confirmada y un contrato de conteo distinto. Los nombres o UUID de registros reservados no se incorporan al agregado para completar una ruta pública.

## SQL, permisos y recursos

La selección usa `EloquentProveedorEspecimenesParaArbol::consultaPublica` con el DTO completo y el taxón navegado; el chat no mantiene una versión reducida del filtro de página. El conteo material agrega filas en SQL. Las métricas taxonómicas recorren identificaciones distintas, sin hidratar la colección ni devolver UUID de ejemplares o ancestros. Géneros y familias respetan `scientific_name_visible` y sus respectivas banderas por registro. Tener un código reservado no impide un agregado taxonómico público si los nombres sí están autorizados.

El CTE confirma cada candidato de rango y sus ancestros conocidos con el criterio común `CalidadDatoPublico::textoValido`: una nota por encima invalida al candidato; una nota solo en la hoja conserva sus padres confirmados. Tanto la diversidad como el ranking de familias conservan esos padres sin contar la nota como especie. Los controles de ciclos y de cadenas que superan treinta nodos permanecen sobre el recorrido completo. Un padre inexistente no se inventa. Esta política coincide con el trabajo de QA4-007 para la jerarquía; el total material de registros conserva el dato original por revisar. Las consultas usan parámetros enlazados. No se ha medido memoria ni latencia en la VM de 1 GB.

## Cobertura propuesta para el paquete

`PortalChatSeleccionQa4Test.php` reutiliza la fixture Chatobius del contrato anterior y añade:

- Un recorrido Livewire con todos los filtros, especie navegada, contexto anterior distinto, borrador pendiente, cambio de vista y ayuda exacta del mapa.
- Casos por dimensión pública, incluidas preparación, técnica, bioma, hábitat, tipo, casta y estadio que el parser del chat no interpreta; cada caso verifica cantidad y enlace.
- Selección vacía autoritativa, consulta global explícita y consulta científica nueva sin contaminación de página o historial.
- Unidades de especies/géneros/familias, listas de diez nombres frente a totales mayores y las formulaciones exactas de QA4 con Arthropoda, Mollusca y Annelida.
- Fechas imposibles o invertidas, límites incompletos, mes inválido, rango incompleto y entradas no escalares, con aclaración y total nulo.
- Exclusión de carga privada/borrador/página y permisos mixtos de identificación/familia/género, conservando material curatorial en registros y excluyendo de diversidad los candidatos no confirmados.
- Hoja con nota curatorial y padres confirmados: diversidad global y de selección, especie nula, ranking de familias con sus registros y retirada de géneros/familias al reservar esas banderas.

Son pruebas Pest de consultas y del componente Livewire; no se duplican en Gherkin. Se revisaron por lectura del código, rutas, permisos, tipos y resultados esperados. No se han ejecutado suites, comprobaciones aisladas ni aplicación. Su validación corresponde al paquete completo autorizado para esta nueva tarea, coordinado por root. El paquete previo no contiene estos cambios y no se afirma un despliegue nuevo. DOTS conserva su control externo independiente; la nueva solicitud autoriza al agente a desplegar la misma identidad del paquete.
