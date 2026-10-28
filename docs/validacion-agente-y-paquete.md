# Validacion del agente y generacion del paquete OCI

La cobertura se conserva y se distribuye por responsabilidad: seis de veinte
familias corresponden al agente (30 %) y catorce al paquete manual (70 %).
Estos porcentajes no representan minutos ni cantidad de casos. El contrato es
`scripts/deployment/plan-validacion.json`.

## Seleccion por impacto

Ambas entradas consumen el mismo plan. No se compara solo el ultimo commit: se
comparan todos los archivos nuevos, modificados y eliminados desde la ultima
base integral aprobada. El mapa sigue consumidores transitivos entre modulos,
referencias PHP, puentes App, pares de interfaces/adaptadores, vistas y sus
renderizadores e imports relativos JavaScript. La seleccion sigue archivos y
componentes; el resumen por modulos no transmite conexiones de otros componentes.
El inventario PHP procede de los directorios configurados en `phpunit.xml`. Un limite
del analisis estatico exige ampliar la validacion, nunca acreditar una omision.

| Cambio | Validacion |
| --- | --- |
| Texto, estilo o componente acotado | Contratos propios y consumidores relacionados |
| Funcion o modulo | Pruebas propias e integraciones directas e indirectas |
| Autenticacion, permisos, dependencias, migraciones o infraestructura compartida | Integral |
| Impacto incierto, archivo sin clasificar, ausencia de base aprobada o cambios amplios | Integral |

El selector es conservador: ante cambios funcionales incluye tambien los
contratos PHP comunes sin propietario. Un ajuste Blade/CSS reconstruye los
cuatro frontends. No garantiza ejecutar el minimo teorico de casos.
La configuracion inicial fuerza integral con mas de veinte archivos acumulados,
mas de dos areas afectadas, base mayor de siete dias, cambio de entorno o
artefactos sin evidencia compatible. Los cambios de reglas invalidan la base.
El runtime debe coincidir con la base o con la ultima validacion completa del
plan, vinculada a esa misma base y linea Git. Las validaciones parciales no
reinician la antiguedad ni la comparacion de cambios acumulados de la base.

**Las auditorias Composer y npm de los cuatro proyectos se ejecutan siempre,
consultando los avisos actuales. La identidad y la integridad del paquete tambien
se verifican siempre.** No se reutiliza una auditoria anterior porque el lock no
haya cambiado. Se conservan los controles estructurales de diff, JSON, contrato
Composer, arranque Laravel, sintaxis, migraciones y asistentes de despliegue.

## Entradas y responsabilidades

Al terminar todos los ajustes de cada solicitud, el agente ejecuta en localhost:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/deployment/validar-cambios-agente.ps1
```

Las directrices autorizan permanentemente esta entrada y sus reintentos completos.
No requiere token ni hace commit, push, paquete o despliegue. Selecciona el impacto,
prepara lo necesario y emite evidencia solo tras completar todos sus controles.
No se ejecutan suites sueltas antes ni despues. Si se trabaja sin agente, el
usuario puede ejecutar esta misma entrada.

| Agente: 30 % | Paquete: 70 % |
| --- | --- |
| Instalacion PHP desde el lock | Diff y conflictos |
| Compilacion Maven y copia del JAR | JSON modificados |
| npm ci en raiz y modulos | Contrato composer.json/lock |
| Builds Vite en raiz y modulos | Auditoria PHP actualizada |
| Autoprueba Java criptografica y lectura QR | Contratos Python ETE |
| Contratos JavaScript Node | Preparacion del runtime ETE |
| | Auditoria npm de los cuatro proyectos |
| | Bash y asistentes DEV/PROD generados |
| | Sintaxis PHP modificados |
| | Clases de migracion |
| | Gherkin `@listo --strict` |
| | Arranque Laravel |
| | Pest/PostgreSQL, PDF local y Neon/Livewire |
| | Runtime, evidencia, identidad, publicacion y archivos OCI |

La tabla establece quien realiza cada control; el plan determina su ejecucion o
reutilizacion. No se eliminan casos ni se debilitan aserciones. PostgreSQL mantiene
bases y almacenamiento aislados. La medicion de firma sigue exclusivamente en
localhost con limite de tres segundos. La autoprueba Java conserva QR a 144 y
220 dpi. Solo el paquete usa el P12 y las credenciales autorizadas de `.local/secrets`.
La revision visual complementaria utiliza Opera Neon QA al 75 %.

## Evidencia

- `.local/validation/base-sectores-agente.json`: bases independientes de las seis
  familias del agente, con fecha real, fuentes propias, comando, ejecutor,
  entorno y artefactos. Se crean al aprobar el 30 %, sin esperar a PHP/PostgreSQL.
  Permiten reutilizar Java y builds aunque todavia falte la primera base integral
  del 70 %. Vencen a los siete dias y no se renuevan las fechas de sectores
  reutilizados. Modificar el selector exige sus contratos Node; no obliga a
  recompilar sectores cuyos insumos siguen identicos. Reconstruir el JAR exige
  comprobarlo nuevamente. `-Integral` fuerza tambien estos sectores.
  La identidad de entorno utiliza los ejecutables resueltos y sus versiones,
  el JDK efectivo y la huella de configuracion; no el texto completo de PATH ni
  las carpetas temporales del lanzador. Codex y la consola manual comparten
  evidencia si resuelven las mismas herramientas. Se conservan metadatos para
  identificar cambios reales de Node, PHP, Java o configuracion.
- `.local/validation/plan-impacto.json`: motivo integral/selectivo, cambios
  acumulados, mapa de dependencias, pruebas a ejecutar/reutilizar y builds.
- `.local/validation/evidencia-agente.json`: acredita el 30 % del plan exacto;
  identifica repositorio, HEAD, fuentes, vendor completo, JAR y cuatro builds.
  Vence a las 24 horas. Un reintento invalida primero el recibo anterior.
- `.local/validation/ultima-validacion.json`: registra lo ejecutado y lo reutilizado
  solo al completar correctamente el 70 %.
- `.local/validation/base-integral.json`: se crea o renueva exclusivamente tras
  completar el 30 % y el 70 % en modalidad integral. Una cancelacion, un fallo o
  solamente el bloque del agente nunca establecen una base integral.

El paquete comprueba la evidencia al inicio y antes de publicar. Cambiar fuentes,
HEAD, plan o runtime exige repetir la entrada completa del agente. Los insumos
se identifican antes de sus suites y se comparan al terminar. Nunca fabricar ni
editar recibos o planes para acreditar controles pendientes. Los recibos del
reparto anterior 40/60 y de reglas anteriores no son compatibles.

## Uso manual del paquete

La publicacion conserva la misma orden y requiere autorizacion expresa:

```powershell
crear-paquete-oci -DescripcionCambio "descripcion del cambio"
```

Para completar el 70 % en localhost sin publicar ni empaquetar:

```powershell
crear-paquete-oci -SoloValidacionLocal -DescripcionCambio "revision local"
```

Ambas exigen evidencia vigente del agente. Gherkin conserva perfil `default`,
etiqueta `@listo` y modo `--strict`: en selectivo ejecuta los archivos relacionados,
con evidencia integral para los restantes. Cualquier fallo bloquea publicacion.
La primera ejecucion sin base compatible es integral.

Para forzar todas las suites, usar primero `validar-cambios-agente.ps1 -Integral`
y despues `crear-paquete-oci -Integral` (opcionalmente `-SoloValidacionLocal`).
Forzar integral en el paquete exige que el agente haya aprobado ese mismo alcance.
GitHub Actions sigue deshabilitado. El agente no ejecuta el paquete sin solicitud
expresa, ni valida o despliega en DEV/PROD por haber validado localmente.

## Progreso y archivos OCI

Cada comando muestra su duracion. Durante PHP se informa cada diez segundos del
tiempo, casos finalizados, fallos, omitidos y caso activo de cada proceso. Los
omitidos se cuentan tambien entre los finalizados. El observador lee mensajes
TeamCity y los archivos de workers del directorio temporal exclusivo de esa
llamada: ParaTest retiene stdout hasta terminar el archivo. La salida definitiva
del registro `--log-teamcity` y el codigo de salida siguen determinando la aprobacion.
Pest consume `--teamcity` antes de ejecutar en paralelo; ese argumento no sirve
para observar esta suite. Los puntos de consola no se cuentan como pruebas aprobadas.
No se inventan porcentajes globales con recuentos parciales de cada worker.
Al terminar se muestran cinco casos lentos y el registro completo en
`.local/deployment/pruebas-php-*.log`. Ctrl+C detiene solo el arbol del proceso
creado por el observador; conserva registro y estado observado. Cancelacion,
salida no cero, fallo notificado, protocolo incompleto o cero casos bloquean
la acreditacion. La limpieza de PostgreSQL informa su fase final.

Se mantienen `artifacts/oci/<descripcion>-<fecha>` y los mismos seis archivos:
bundle `.tar.gz`, `.sha256`, kit Cloud Shell, asistentes DEV/PROD e instrucciones.
La evidencia y el mapa son locales; no se agregan al paquete. Se conservan
SOURCE-METADATA, SOURCE-MANIFEST, el commit publicado en `origin/main`, vendor de
produccion aislado y construccion nativa Linux de la VM. Crear el paquete no
autoriza subirlo ni activarlo. Esta politica sustituye referencias historicas
a ejecutar todas las suites en cada llamada al paquete.

## Navegador por casos con evidencia independiente

`.local/validation/base-navegador.json` conserva aprobaciones propias de nueve recorridos: apertura/sesion/cabecera/pie, filtros/busqueda/graficos, tooltip/Livewire, arbol/diversidad, modales/mapa/registros, tabla/paginacion, fotografias, administracion/importacion y PDF de siete figuras. El selector registra casos ejecutados, reutilizados y motivos en el plan; no repetir UI intacta aunque Pest no haya completado su base integral. Cada caso prepara su estado sin ejecutar las aserciones de otros casos.

Las entradas propias, fixture, vendor y entorno deben coincidir y la evidencia dura siete dias; reutilizar conserva la fecha real. La migracion del OK completo confirmado exige runner original, cuerpos completos y todas sus aserciones conservadas, fixture y fuentes compatibles. Su procedencia declara expresamente que el runner nuevo no se ejecuto. No acredita Pest ni crea una base integral del 70 %. Un caso cancelado o fallido no queda aprobado; los que si terminaron conservan su evidencia individual.

Cambios de empaquetado o selector por si solos no disparan UI. Se siguen consumidores directos e indirectos. Los metodos de tooltip del dashboard y los metodos conocidos del controlador se comparan por fragmento; un componente compartido sin fragmento seguro amplia los casos y explica el motivo. Autenticacion, permisos y dependencias compartidas alcanzan todos los consumidores. `-Integral` fuerza los nueve recorridos. Las auditorias actuales y la integridad OCI siguen obligatorias.


Las bases propias ETE/Python y PDF PHP/Java se guardan en `.local/validation/base-sectores-paquete.json` al aprobar cada sector. No esperan al cierre de Pest. Se conservan las fechas originales y se invalidan antes de reintentar; no acreditan PHP ni Gherkin. ETE identifica tambien el interprete y todo su entorno virtual. El OK historico de PDF puede conservarse con fuentes, vendor, JAR, entorno y transcripcion compatibles: su certificado es sintetico y temporal. El avance cancelado conserva 11 casos Python aprobados como resultado historico; sin huella previa del runtime ETE, su base reutilizable se establece tras la siguiente prueba. El plan de navegador y PDF se actualiza con los artefactos ya construidos antes de sellar las pruebas del agente, sin repetir su calculo de huellas.
