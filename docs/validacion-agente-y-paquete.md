# Validacion del agente y generacion del paquete OCI

La cobertura se conserva y se distribuye por responsabilidad: seis de veinte
familias corresponden al agente (30 %) y catorce al paquete manual (70 %).
Estos porcentajes no representan minutos ni cantidad de casos. El contrato es
`scripts/deployment/plan-validacion.json`.

## Seleccion por impacto

Ambas entradas consumen el mismo plan. No se compara solo el ultimo commit: se
comparan todos los archivos nuevos, modificados y eliminados desde la ultima
base aprobada local. El paquete selecciona exclusivamente pruebas propias de las
fuentes cambiadas, incluidas referencias literales al archivo bajo prueba e imports
directos. El grafo transitivo se conserva como diagnostico y no agrega consumidores
ni otros dominios a las pruebas. El inventario PHP procede de `phpunit.xml`.
Si falta cobertura mapeada, el proceso indica los archivos pendientes y se detiene;
no cambia automaticamente a una suite integral ni acredita una omision.

| Cambio | Validacion |
| --- | --- |
| Texto, estilo o componente acotado | Contratos propios del cambio; build solo del proyecto modificado |
| Funcion o modulo | Pruebas que cubren directamente la fuente cambiada |
| Autenticacion, permisos, dependencias, migraciones o infraestructura compartida | Contratos propios; sin expansion integral automatica |
| Cobertura sin mapear o ausencia de base aprobada compatible | Detener e indicar lo pendiente; nunca ejecutar todo por defecto |
| Solicitud expresa con `-Integral` | Todas las suites |

La cantidad de archivos, la antiguedad de la base, un cambio de selector o una
diferencia de entorno no fuerzan una revision integral. Se conserva la comprobacion
de identidad de fuentes y runtime del estado actual. ETE/Python, Laravel,
migraciones, PDF, UI y guias generadas solo se prueban cuando el plan del cambio
los selecciona. Python y Gherkin reciben los archivos seleccionados, sin expandirse
a todo el dominio. En UI se seleccionan recursos o fragmentos reconocidos;
un fragmento desconocido queda pendiente en lugar de abrir los nueve recorridos.

Tras aprobar todos los controles seleccionados, se actualiza
`.local/validation/base-integral.json` tambien en las ejecuciones incrementales.
La nueva base incorpora las huellas del cambio aprobado y la cobertura heredada;
conserva la fecha de la aprobacion integral original y la procedencia de la base
anterior. La proxima ejecucion compara contra esta nueva base, de modo que no
repite cambios ya aprobados. Un fallo o cancelacion de las pruebas no la actualiza.
La acreditacion ocurre antes del commit/empaquetado: un fallo posterior de Git o
de transferencia no invalida pruebas que ya terminaron correctamente.
La base y los recibos son SOLO LOCALES: `.local` esta ignorado por Git y excluido
del bundle OCI. No se publican ni se transfieren a DEV/PROD.

**Las auditorias Composer y npm de los cuatro proyectos se ejecutan siempre,
consultando los avisos actuales. La identidad y la integridad del paquete tambien
se verifican siempre.** No se reutiliza una auditoria anterior porque el lock no
haya cambiado. Se conservan los controles estructurales de diff, JSON, contrato
Composer y sintaxis de los archivos cambiados. Arranque Laravel, migraciones y
asistentes generados se subordinan al plan del cambio.

## Entradas y responsabilidades

Al terminar todos los ajustes de cada solicitud, el agente ejecuta en localhost:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/deployment/validar-cambios-agente.ps1
```

Las directrices autorizan permanentemente las comprobaciones locales acotadas y sus reintentos pertinentes.
No requiere token ni hace commit, push, paquete o despliegue. Selecciona el impacto,
prepara lo necesario y emite evidencia solo tras completar todos sus controles.
Para comprobar el desarrollo se permiten casos locales filtrados cuando la entrada
no permite acotar suficientemente. El agente revisa tambien los dependientes
concretamente afectados; el paquete automatico mantiene la seleccion exclusiva
del cambio. `validar-cambios-agente.ps1 -SoloSelectorPaquete` comprueba solo este
selector, el alcance de UI y el avance de la base, sin ejecutar el sistema completo.
Si se trabaja sin agente, el usuario puede ejecutar esta misma entrada.

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
Sin una base compatible se solicita restaurarla o autorizar `-Integral`; no se inicia integral automaticamente.

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

`-Destino W` mantiene `artifacts/oci/<descripcion>-<fecha>` con seis archivos:
bundle `.tar.gz`, `.sha256`, asistentes DEV/PROD, entorno privado DEV e instrucciones.
No se genera `cloudshell-upload.tar.gz`: DEV recibe directamente el bundle,
su checksum, el asistente DEV y el entorno. El `.env` separado contiene secretos;
no se versiona, no se comparte y no se usa en PROD. Los checksums del bundle,
asistente y entorno se conservan; el asistente elimina las copias transferidas
de Cloud Shell y `/tmp` al completar correctamente cada etapa.
Los archivos a transferir llevan su destino en el nombre: `AMBOS` para el
bundle compartido; `DEV` para checksum, asistente y entorno de desarrollo;
`PROD` para el asistente de produccion. El checksum DEV verifica tambien
su script y entorno; PROD comprueba el hash del bundle mediante su asistente,
sin recibir archivos DEV. El manual se conserva localmente.
`-Destino LD` es el valor predeterminado y copia directamente los cuatro archivos
DEV/AMBOS a `ubuntu@129.153.23.57:~/TEMP-BORRAR/<paquete>/`; `-Destino LP` copia
solo los dos PROD/AMBOS a `ubuntu@150.136.219.112` en la misma estructura.
No se usa Cloud Shell. Se comprueba SSH antes de las pruebas/publicacion;
cada transferencia tiene limite de tiempo, progreso y verificacion SHA-256
de todos los archivos recibidos. La copia local de `.local/deployment/oci-transfer`
se retira solo tras verificar la recepcion completa; la guia se conserva en
`artifacts/oci`. Si falla, reintentar `destino-paquete.cjs copiar PROYECTO LD|LP CARPETA`
no repite pruebas, publicacion ni empaquetado.
La VM conserva TEMP-BORRAR hasta finalizar correctamente la activacion. DEV
limpia al terminar `activate-release.sh`; PROD espera ademas su aceptacion final
`PRODUCTION_OK`. Se eliminan los archivos enumerados y la carpeta del paquete;
se elimina tambien TEMP-BORRAR cuando no hay otras transferencias pendientes.
No se borran enlaces, archivos ajenos ni respaldos. Si falla el despliegue se
conservan los temporales. La copia nunca prepara ni activa la release.
`validar-cambios-agente.ps1 -SoloDestinoPaquete` comprueba solo los contratos
nuevos de seleccion, transferencia, fallos y limpieza local con SSH simulado;
no renueva evidencia general del 30 %, ni accede a las VM. El cambio no exige
reinstalar dependencias, recompilar Java/Vite ni repetir las suites funcionales.
La evidencia y el mapa son locales; no se agregan al paquete. Se conservan
SOURCE-METADATA, SOURCE-MANIFEST, el commit publicado en `origin/main`, vendor de
produccion aislado y construccion nativa Linux de la VM. LD/LP incluyen solo la
copia al destino seleccionado. La preparacion se inicia manualmente en la VM
y encadena la activacion automaticamente solo tras aprobar todos sus controles.
DEV conserva las puertas de identidad, migraciones, Java, PDF, asistente y HTTP.
PROD difiere internamente la activacion generica hasta registrar PREPARED_OK,
y luego ejecuta su accion activar completa (retorno a produccion, servicios,
HTTP y limpieza final). Un fallo no activa ni provoca reintentos automaticos.
`validar-cambios-agente.ps1 -SoloPreparacionActivacion` prueba localmente esa
transicion con los bloques Bash reales y activadores sinteticos; no despliega,
no ejecuta suites ajenas ni acredita el 30 % completo.
Esta politica sustituye referencias historicas
a ejecutar todas las suites en cada llamada al paquete.

## Navegador por casos con evidencia independiente

`.local/validation/base-navegador.json` conserva aprobaciones propias de nueve recorridos: apertura/sesion/cabecera/pie, filtros/busqueda/graficos, tooltip/Livewire, arbol/diversidad, modales/mapa/registros, tabla/paginacion, fotografias, administracion/importacion y PDF de siete figuras. El selector registra casos ejecutados, reutilizados y motivos en el plan; no repetir UI intacta aunque Pest no haya completado su base integral. Cada caso prepara su estado sin ejecutar las aserciones de otros casos.

El paquete limita la seleccion a los casos propios del cambio; la antiguedad de los recorridos intactos no los vuelve a ejecutar. Reutilizar conserva la fecha real y la aprobacion historica, sin declarar ejecutadas sus aserciones sobre el cambio nuevo. La migracion del OK completo confirmado exige runner original, cuerpos completos y todas sus aserciones conservadas, fixture y fuentes compatibles. Su procedencia declara expresamente que el runner nuevo no se ejecuto. No acredita Pest ni crea una base integral del 70 %. Un caso cancelado o fallido no queda aprobado; los que si terminaron conservan su evidencia individual.

Cambios de empaquetado o selector por si solos no disparan UI. El paquete no sigue consumidores transitivos para agregar recorridos. Los metodos de tooltip del dashboard y los metodos conocidos del controlador se comparan por fragmento; un componente sin fragmento o contrato propio delimitado queda pendiente y no activa los nueve casos. `-Integral` fuerza los nueve recorridos solo por solicitud expresa. Las auditorias actuales y la integridad OCI siguen obligatorias.


Las bases propias ETE/Python y PDF PHP/Java se guardan en `.local/validation/base-sectores-paquete.json` al aprobar cada sector. No esperan al cierre de Pest. Se conservan las fechas originales y se invalidan antes de reintentar; no acreditan PHP ni Gherkin. ETE identifica tambien el interprete y todo su entorno virtual. El OK historico de PDF puede conservarse con fuentes, vendor, JAR, entorno y transcripcion compatibles: su certificado es sintetico y temporal. El avance cancelado conserva 11 casos Python aprobados como resultado historico; sin huella previa del runtime ETE, su base reutilizable se establece tras la siguiente prueba. El plan de navegador y PDF se actualiza con los artefactos ya construidos antes de sellar las pruebas del agente, sin repetir su calculo de huellas.
