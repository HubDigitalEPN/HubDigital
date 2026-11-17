# Aislamiento del transporte R2

El 13 de noviembre de 2026 a las 01:20 de Ecuador, las actualizaciones de Ubuntu
reiniciaron red y DNS mientras Nginx intentaba resolver el upstream R2. El include
`/etc/nginx/conf.d/hubdigital-r2-transport.conf` hizo fallar toda la configuración
con `host not found in upstream`. No quedó ningún listener HTTPS. La PWA mostró
su pantalla de desconexión porque el servidor no respondía.

El mismo incidente se recuperó el 5 de noviembre. La prevención se detuvo entonces
a petición del usuario; la rama `fix/nginx-r2-dns-20261105` quedó sin cambios.

La reparación del 16 de noviembre separa R2 en `hubdigital-r2-transport.service`,
con configuración `/etc/hubdigital/r2-transport.conf` y PID propio. El frontal no
incluye ese upstream ni depende de su servicio. Se mantienen keepalive, SNI,
verificación TLS, métodos autorizados y ausencia de caché documental. El socket
privado es `0600 www-data:www-data`. El servicio restaura el host público en `/run`
desde `/etc/hubdigital/r2-origin-host`; no copia credenciales. Los reinicios ante
fallos se limitan a tres arranques por cinco minutos.

`configure-r2-transport.php`, la instalación base y la activación conservan esa
separación en futuras releases. La reparación operativa se puede aplicar como
root con `deploy/oracle/scripts/repair-r2-dns-isolation.sh` desde fuentes revisadas.
Hace respaldo y revierte si falla la recarga; no modifica la release de aplicación.

El socket es una optimización de conexiones TLS, no el almacenamiento. Los
archivos siguen guardándose en el mismo bucket privado de R2. El cliente común
`R2S3Client` cambia a HTTPS directo si el socket falla o devuelve un error 5xx;
conserva host, firma SigV4, ruta, consulta y bytes, verifica TLS y no sigue
redirecciones. El cambio de transporte consume el presupuesto existente de
como máximo tres intentos, y las siguientes operaciones de ese cliente evitan
el socket averiado. Un 403 o 404 no se oculta mediante reintentos de transporte.

La corrección cubre documentos e imágenes porque `StorageImagenesAdapter`
utiliza `AlmacenamientoDepositos`, que comparte ese cliente. El guardado mantiene
la lectura posterior y la comprobación de tamaño/SHA-256 antes de confirmarse.
Si R2 y el acceso directo están indisponibles, se informa el fallo: no se declara
guardada una imagen inexistente ni se usa una copia local silenciosa. Los
documentos admitidos por la outbox conservan sus bytes cifrados pendientes hasta
su entrega confirmada; el aislamiento no elimina ni confirma esos pendientes.

En PROD se conservó `/srv/hubdigital/releases/d15249930ced62b1`. El respaldo está
en `/var/backups/hubdigital/r2-dns.vsjgiHrz`. La aceptación comprobó ambos servicios
activos, permisos, `nginx -t`, HTTP 200 en Inicio y catálogo, TLS válido y catálogo
visible en Neon QA al 75 %, sin errores de consola. Una petición R2 sin firma
obtuvo HTTP 400, comprobando el transporte sin acceder a documentos privados.

## Pruebas locales

`tests/Frontend/r2-transport-isolation.test.mjs` cubre la instalación y la
reversión con servicios simulados. `tests/Frontend/nginx-r2-isolation.test.mjs`
usa Nginx real y HTTPS sintético: DNS inexistente con frontal disponible,
GET/HEAD/PUT/DELETE, URI, Host, autorización, keepalive, métodos prohibidos y
rechazo de un certificado con nombre distinto.

La prueba real requiere Nginx y OpenSSL disponibles, o las variables
`HUBDIGITAL_NGINX_EXE` y `HUBDIGITAL_OPENSSL`. `HUBDIGITAL_QA_TMPDIR` permite fijar
el directorio temporal. No descarga runtimes. En Windows adapta usuario, rutas
y socket a loopback; no acredita un reinicio real de Linux. El Nginx 1.24 portátil
usado localmente procede de nginx.org; SHA256 del ZIP:
`69a36bfd2a61d7a736fafd392708bd0fb6cf15d741f8028fe6d8bb5ebd670eb9`.

## Trabajo pendiente integrado para OCI

La rama de integración conserva los cambios de roles, depósitos, recepción QR,
documento de ingreso, captura DwC y esta reparación. Se respaldaron previamente
los cambios sin registrar y sus archivos nuevos en `.local/backups`.

`feature/coleccion-biologica-investigacion-20260930` tiene un único parche
`8688d9c3` que `git cherry main` identifica ya incorporado. El snapshot
`35b73694` de `fix/navegador-por-casos-20261028` contiene versiones anteriores de
los selectores y avances locales, ya desarrollados en main. Sus pruebas que
forzaban integral por incertidumbre contradicen el acuerdo actual. Su Java QR
también es anterior a la corrección `6fd21915`. Se conservan las versiones
actuales y sus aserciones adicionales; no se reincorporan esas versiones antiguas.

La auditoría actual del paquete también exigió parches de dependencias:
Laravel 13.40.0 y Symfony config/filesystem/http-foundation/http-kernel/routing/yaml
8.1.11 corrigen los diez avisos de Composer. Los locks npm incorporan
fast-xml-parser 5.12.1 y follow-redirects 1.16.3 en los tres módulos. No se agregan
dependencias directas ni se omiten auditorías. La instalación, los builds y la
comprobación posterior corresponden a las mismas entradas de validación local.
