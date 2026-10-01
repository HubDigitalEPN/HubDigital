# QA del portal

## RESUMEN ACTUAL
2026-10-01: auditoría bloqueada antes de navegar. No hay hallazgos del portal confirmados. Repositorio confirmado: C:\HT\LABINVEPN\HubDigital; remoto oficial HubDigitalEPN/HubDigital. deploy/oracle/nginx/hubdigital.conf y deploy/oracle/env/hubdigital.env.example referencian dev.labinvepn.org. No es KintiFlow. Se leyeron ambos AGENTS.md. QA_PORTAL.md no existía y .agents no contiene habilidades locales.

## PRIORIDAD INMEDIATA
Habilitar las herramientas de navegador en la sesión. Browser requiere node_repl js; no está expuesta ni hay herramienta de descubrimiento disponible. No se sustituyó la navegación real por HTTP ni código.

## LISTOS PARA CODEX
Ninguno: no hubo reproducción en el portal. No se encargaron implementaciones.

## PENDIENTES DE INVESTIGACIÓN
Toda la matriz solicitada: navegación pública e historial; fichas/regreso; mapa, zoom, desplazamiento, marcadores/popups; vistas y coherencia de datos; búsqueda; filtros individuales/combinados, raros, cero resultados, limpieza y persistencia; chatbot con transcripciones exactas (ortografía, tildes, coloquialismos, ambigüedad, negación, seguimiento/contexto, catálogo/filtros y fuera de dominio); UX/accesibilidad; errores JS/red/HTTP.
Resoluciones pendientes: 1920x1080, 1440x900, 1366x768, 1024x768, tablet y móvil. Ninguna probada.

## CORREGIDOS POR VERIFICAR
Ninguno registrado. Los cambios locales no prueban el estado desplegado.

## REGRESIONES
Ninguna comprobada. Falta línea base navegada.

## BACKLOG DETALLADO
Sin IDs asignados. Reservar QA-001 para el primer hallazgo real; el bloqueo de herramientas no es un bug del portal.
Cada incidencia deberá conservar: ID, detección, estado, severidad, área, clasificación (confirmado/sospecha/mejora), URL, reproducción exacta, actual/esperado, impacto, evidencia, hipótesis fundada, recomendación y última comprobación. No duplicar IDs.
Las tareas Codex deberán incluir objetivo, observado, reproducción, esperado, componentes solo con evidencia, restricciones, aceptación y pruebas. No marcar CORREGIDO sin comprobar el portal desplegado.

## HISTORIAL
2026-10-01: preparación del registro; navegación 0, conversaciones 0, capturas 0. Rama existente fix/catalogo-filtros-mapa-chat-20261001 con cambios pendientes en catálogo/chat/filtros y otros componentes; no se modificaron. No se ejecutaron suites ni crear-paquete-oci, commits/push, instalaciones, cambios de ramas, datos o producción. Único cambio: este documento. Retomar desde https://dev.labinvepn.org/portal/catalogo?vista=mapa cuando las herramientas estén disponibles.

### 2026-10-01 - segunda comprobacion del bloqueo

Estado: AUDITORIA INCOMPLETA, sin navegacion ni conclusiones sobre el producto.

Inventario comprobado de herramientas expuestas: exec_command, write_stdin, view_image, skills__read y web__run disponibles. No hay node_repl js/js_reset, tool_search, browser-control, Playwright MCP ni acciones Computer Use (list_apps/get_window_state/click). web__run no controla el navegador Windows ni permite probar estos flujos interactivos; view_image solo inspecciona imagenes existentes.

Instalacion local: browser-client.mjs existe; habilidades Browser y Computer Use existen; node.exe y npm existen. package.json del proyecto solo incluye scripts build/dev y dependencias Vite/Tailwind/Chart.js/Leaflet; no declara Playwright. No se encontro un directorio playwright en node_modules del proyecto. No se afirma que no exista Playwright en ninguna otra ubicacion del equipo.

Instrucciones leidas: Computer Use docs/guidance.md exige node_repl para todas las acciones. Browser exige Node REPL js y prohibe inventar otra ruta de control para esa superficie. frontend-testing-debugging considera la falta de invocacion un bloqueo Browser y no permite usar Playwright primero cuando Browser esta disponible. No se ejecutaron automatizaciones PowerShell de UI, CDP, runtimes alternativos ni instalaciones para eludir estas restricciones.

Relanzar no garantiza resolver nada: solo seria util si la nueva sesion expone node_repl js (y permite seleccionar una instancia conectada) o proporciona explicitamente una alternativa permitida. No se comprobo desconexion del navegador; el bloqueo ocurre antes de poder invocar su selector.

Se volvio a leer QA_PORTAL.md y se confirmaron sus seis secciones iniciales, backlog sin incidencias ficticias y estado pendiente. No se reemplazo el historial.
