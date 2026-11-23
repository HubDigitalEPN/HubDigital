# Validacion del agente y generacion del paquete OCI

Si cambia una entrada compartida despues de conservar archivos PHP completos,
`php-parcial.cjs delimitar PROYECTO FUENTES_ORIGINALES.json` verifica primero
que las fuentes originales, runtime y entorno corresponden exactamente a la huella
del runner. Exige contratos propios para cada fuente cambiada y retira solo sus
archivos afectados o aquellos cuyo cuerpo cambio. Conserva las fechas y procedencia
del resto y registra las dos huellas y el alcance en el punto de avance. Una fuente
sin mapa, un cambio de soporte de pruebas o de runtime/entorno detiene esta accion.
Esta delimitacion acredita compatibilidad; no declara ejecutadas pruebas nuevas.
La unica equivalencia de runtime automatica admite el JSON de resultados generado
por Pest dentro de `vendor/pestphp/pest/.temp/test-results`: exige igual cantidad de
archivos y los mismos builds y entorno; inspecciona todo vendor desde el sello
original y se detiene si otra biblioteca fue escrita, aparece un enlace o el archivo
no es JSON. El informe conserva expresamente este motivo. Las pruebas focales deben
usar `--cache-directory` fuera de vendor, como ya hace el runner del paquete.

Cambiar el selector UI u otro caso no obliga a repetir recorridos intactos.
`preserveCompatible` exige el runner original completo con la misma huella del
recibo, las fuentes propias y build iguales, y el cuerpo y ayudas del caso sin
cambios. Conserva el recibo original sin escribirlo ni renovar su fecha; guarda
un testigo separado en `ui-focal/compatibilidad`. Un cambio de aserciones, fuentes
o ayudas utilizadas obliga a ejecutar de nuevo ese caso mediante Neon.

El comando OCI compila y valida en una sola ejecucion desde el repositorio de trabajo
de HubDigital. Conserva las dependencias compatibles con los locks.

## Seleccion por impacto

Ambas entradas consumen el mismo plan. No se compara solo el ultimo commit: se
comparan todos los archivos nuevos, modificados y eliminados desde la ultima
base aprobada local. El paquete selecciona exclusivamente pruebas propias de las
fuentes cambiadas, incluidas referencias literales al archivo bajo prueba e imports
directos. El grafo transitivo se conserva como diagnostico y no agrega consumidores
ni otros dominios a las pruebas. El inventario PHP tecnico procede de las pruebas unitarias declaradas en `phpunit.xml`. Los archivos PHP y JavaScript modificados pasan ademas por una comprobacion de sintaxis.
Si falta cobertura mapeada, el proceso indica los archivos pendientes y se detiene;
no cambia automaticamente a una suite integral ni acredita una omision.

| Cambio | Validacion |
| --- | --- |
| Texto, estilo o componente acotado | Contratos propios del cambio; build solo del proyecto modificado |
| Funcion o modulo | Pruebas que cubren directamente la fuente cambiada |
| Autenticacion, permisos, dependencias, migraciones o infraestructura compartida | Contratos propios; sin expansion integral automatica |
| Cobertura sin mapear o ausencia de base aprobada compatible | Detener e indicar lo pendiente; nunca ejecutar todo por defecto |
| Solicitud expresa con `-Integral` | Todos los controles tecnicos del paquete |

La cantidad de archivos, la antiguedad de la base, un cambio de selector o una
diferencia de entorno no fuerzan una revision integral. Se conserva la comprobacion
de identidad de fuentes y runtime del estado actual. ETE/Python, Laravel,
migraciones, PDF y guias generadas se comprueban segun el plan del cambio.
El paquete ejecuta pruebas unitarias, de integración, de servicios y contratos,
de base de datos, de carga y rendimiento y de mutación. PostgreSQL usa un esquema
aislado sin copiar cuentas. La carga y las mutaciones de la cuadrícula se prueban
en memoria, sin navegador. El inicio de sesión y los recorridos funcionales del
sistema quedan para la revisión independiente.

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

El paquete compila el codigo actual y ejecuta sus controles tecnicos en la misma orden.
`validar-cambios-agente.ps1` queda disponible para pruebas acotadas del desarrollo.

| Compilacion y pruebas de construccion | Otros controles tecnicos del paquete |
| --- | --- |
| Instalacion PHP desde el lock | Diff y conflictos |
| Compilacion Maven y copia del JAR | JSON modificados |
| npm ci en raiz y modulos | Contrato composer.json/lock |
| Builds Vite en raiz y modulos | Auditoria PHP actualizada |
| Autoprueba Java criptografica y lectura QR | Contratos Python ETE |
| Contratos JavaScript Node | Preparacion del runtime ETE |
| | Auditoria npm de los cuatro proyectos |
| | Bash y asistentes DEV/PROD generados |
| | Sintaxis de PHP y JavaScript modificados |
| | Clases de migracion |
| | Pruebas unitarias PHP |
| | Arranque Laravel |
| | Control tecnico PDF y firma PHP/Java local |
| | Runtime, evidencia, identidad, publicacion y archivos OCI |

La tabla establece quien realiza cada control; el plan determina su ejecucion o
reutilizacion. Las pruebas funcionales se conservan para ejecucion independiente.
La medicion tecnica de firma usa PDF y certificados sinteticos en localhost
y conserva el limite de tres segundos. La revision visual complementaria
utiliza Opera Neon QA al 75 %.

## Evidencia

El plan, las huellas del codigo compilado, los resultados y la base aprobada
se guardan en `.local/validation`. El paquete genera estos registros durante su
propia ejecucion y conserva el commit y las huellas de los archivos entregados.
Las compilaciones parten del repositorio de trabajo HubDigital. Antes de compilar,
el comando guarda un ZIP verificado de las fuentes y sus configuraciones en
`%LOCALAPPDATA%\HubDigital\Respaldos\codigo-fuente`. Esta carpeta local de Windows
queda fuera de cualquier repositorio Git, del paquete OCI y de las VM.

## Uso manual del paquete

La publicacion conserva la misma orden y requiere autorizacion expresa:

```powershell
crear-paquete-oci -DescripcionCambio "descripcion del cambio"
```

Para compilar y comprobar en localhost sin publicar ni empaquetar:

```powershell
crear-paquete-oci -SoloValidacionLocal -DescripcionCambio "revision local"
```

Cualquier fallo tecnico bloquea la publicacion.
Sin una base compatible se solicita restaurarla o autorizar `-Integral`; no se inicia integral automaticamente.

Para forzar todos los controles tecnicos, usar `crear-paquete-oci -Integral` (opcionalmente `-SoloValidacionLocal`).
GitHub Actions sigue deshabilitado. El agente no ejecuta el paquete sin solicitud
expresa, ni valida o despliega en DEV/PROD por haber validado localmente.

## Progreso y archivos OCI

Cada comando muestra su duracion. Pest ejecuta los archivos unitarios
seleccionados; los controles PDF conservan su evidencia independiente.
Las pruebas funcionales no se ejecutan como parte de esta operacion.

`-Destino W` es el valor predeterminado y mantiene `artifacts/oci/<descripcion>-<fecha>` con seis archivos:
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

## Pruebas visuales independientes del paquete

Las pruebas con Opera Neon se realizan en localhost por separado. `crear-paquete-oci` no lanza el navegador ni exige recibos de UI focal o del historial de navegador, tampoco con `-Integral`. El inicio de sesion y las validaciones funcionales del sistema se comprueban aparte; el paquete ejecuta controles tecnicos. Las evidencias visuales existentes conservan sus fechas y su alcance original.

Las bases propias ETE/Python y PDF PHP/Java se guardan en `.local/validation/base-sectores-paquete.json` al aprobar cada sector. No esperan al cierre de Pest. Se conservan las fechas originales y se invalidan antes de reintentar; no acreditan PHP ni Gherkin. ETE identifica tambien el interprete y todo su entorno virtual. El OK historico de PDF puede conservarse con fuentes, vendor, JAR, entorno y transcripcion compatibles: su certificado es sintetico y temporal. El avance cancelado conserva 11 casos Python aprobados como resultado historico; sin huella previa del runtime ETE, su base reutilizable se establece tras la siguiente prueba. El plan de PDF se actualiza con los artefactos ya construidos antes de sellar las pruebas del agente, sin repetir su calculo de huellas.
