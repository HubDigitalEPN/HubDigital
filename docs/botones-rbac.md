# Botones y perfiles

Administración → Botones y permisos permite configurar por perfil activo ocho
controles: Excel completo, IPT, resultados CSV, análisis CSV, lista de especies,
Excel del inventario, panel del curador y reporte de depósitos. Cada permiso
distingue Ver y Usar; usar exige ver. El servidor consulta la misma matriz y
una revocación se aplica en la siguiente petición.

El catálogo completo y controles internos admiten Administración, Curador y
Ayudante de curador. IPT empieza habilitado solo para ese equipo. Administración
puede habilitar IPT para otros perfiles, conservando las reglas públicas y el
interruptor general de autoservicio. El Excel externo omite notas internas y
columnas personalizadas. CSV e IPT externos exigen revisión confirmada y nueve
datos: occurrenceID, locatedAt, orden confirmado, preparations, country,
stateProvince, localityName y dos coordenadas válidas. Conservan publicación y
visibilidad de campos. Consulta pública y agregados mantienen su comportamiento.

Usuarios y perfiles permite asignar varios perfiles internos y de autoservicio.
El principal permanece entre los asignados. Configuración de cuenta → Roles
permite cambiarlos; el portal también ofrece Cambiar perfil. Las áreas conservan
sus permisos de dominio. Solo Administración gestiona permisos y usuarios;
Ayudante de curador no obtiene ese permiso. El registro público rechaza perfiles
internos; solo un administrador puede asignar Administración a un correo externo.
Curaduría y recepción requieren correo institucional al crearlos o editarlos.

`usuarios.permisos_botones` guarda excepciones a `config/botones.php`, autor y
fecha. La edición concurrente se rechaza; un fallo de consulta deniega permiso.
Administrar la matriz permanece accesible a ADMIN para recuperar configuraciones.

Asignación solicitada en DEV y PROD después de migrar:

```sh
php artisan usuarios:asignar-perfiles adrian.troya@epn.edu.ec CURADOR ADMIN
php artisan usuarios:asignar-perfiles hernan.troya@yahoo.com ADMIN
```

El comando conserva contraseña, verificación, datos y perfil principal. No crea
cuentas ausentes. Vladimir no existía en PROD el 28/10/2026; Adrián puede crear su
cuenta institucional como Ayudante de curador desde Usuarios y perfiles.

XLSX usa `storage/app/private/exportaciones` y limpia temporales al terminar o
fallar. Evita agotar `/run/hubdigital/php-tmp`, que causaba el error 500 del Excel.
